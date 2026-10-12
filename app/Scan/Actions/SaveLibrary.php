<?php

namespace App\Scan\Actions;

use App\Models\Library;
use App\Models\LibraryPath;
use App\Scan\LibraryPathResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveLibrary
{
    public function __construct(private LibraryPathResolver $paths, private RequestLibraryScan $requestScan) {}

    /**
     * @param  array{name: string, automatic_scanning: bool, scan_interval_minutes: int, exclusion_patterns: list<string>}  $attributes
     * @param  list<string>  $paths
     */
    public function handle(?int $libraryId, array $attributes, array $paths): Library
    {
        $retained = $libraryId === null ? [] : LibraryPath::query()
            ->where('library_id', $libraryId)->pluck('path')->all();
        $canonical = [];

        foreach ($paths as $index => $path) {
            $path = trim($path);
            $canonical[] = in_array($path, $retained, true)
                ? $path
                : $this->paths->canonical($path, 'paths.'.$index);
        }

        $library = Cache::lock('library-path-management', 30)->block(5, function () use ($libraryId, $attributes, $canonical): Library {
            return DB::transaction(function () use ($libraryId, $attributes, $canonical): Library {
                $library = $libraryId === null ? new Library : Library::query()->findOrFail($libraryId);
                $others = LibraryPath::query()
                    ->when($libraryId !== null, fn ($query) => $query->where('library_id', '!=', $libraryId))
                    ->pluck('path')->all();
                $accepted = [];

                foreach ($canonical as $index => $path) {
                    foreach ([...$others, ...$accepted] as $existing) {
                        if ($this->paths->overlaps($path, $existing)) {
                            throw ValidationException::withMessages([
                                'paths.'.$index => 'This folder duplicates or overlaps another configured folder: '.$existing,
                            ]);
                        }
                    }

                    $accepted[] = $path;
                }

                $library->fill($attributes)->save();
                $library->paths()->whereNotIn('path', $canonical)->delete();

                foreach ($canonical as $path) {
                    $library->paths()->firstOrCreate(
                        ['path_hash' => hash('sha256', $path)],
                        ['path' => $path],
                    );
                }

                return $library;
            });

        });

        $this->requestScan->forNewPaths($library);

        return $library;
    }
}
