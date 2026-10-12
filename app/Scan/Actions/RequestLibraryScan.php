<?php

namespace App\Scan\Actions;

use App\Jobs\ScanLibraryPath;
use App\Models\Library;
use App\Models\LibraryPath;
use App\Scan\Enums\ScanStatus;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RequestLibraryScan
{
    public function __construct(private Dispatcher $bus, private Repository $cache) {}

    /** @param list<int>|null $pathIds */
    public function handle(Library $library, ?array $pathIds = null): int
    {
        return Cache::lock('library-scan-dispatch:'.$library->id, 30)->block(5, function () use ($library, $pathIds): int {
            return DB::transaction(function () use ($library, $pathIds): int {
                $library = Library::query()->find($library->id);

                if ($library === null) {
                    return 0;
                }

                $paths = $library->paths()
                    ->when($pathIds !== null, fn ($query) => $query->whereIn('id', $pathIds))
                    ->whereNotIn('scan_status', [ScanStatus::Queued, ScanStatus::Scanning])
                    ->get();

                foreach ($paths as $path) {
                    $token = (string) Str::uuid();
                    $path->update([
                        'scan_status' => ScanStatus::Queued,
                        'scan_token' => $token,
                        'scan_requested_at' => now(),
                        'scan_started_at' => null,
                        'scan_finished_at' => null,
                        'scan_error' => null,
                    ]);
                    $job = new ScanLibraryPath($path->id, $token, $library->exclusion_patterns);

                    DB::afterCommit(fn () => $this->enqueue($job));
                }

                if ($paths->isNotEmpty()) {
                    $library->update(['last_scan_requested_at' => now()]);
                }

                return $paths->count();
            });
        });
    }

    public function forNewPaths(Library $library): int
    {
        $pathIds = array_values($library->paths()->where('scan_status', ScanStatus::Idle)->get(['id'])
            ->map(fn (LibraryPath $path): int => $path->id)->all());

        return $this->handle($library, $pathIds);
    }

    private function enqueue(ScanLibraryPath $job): void
    {
        if (! LibraryPath::query()->whereKey($job->pathId)->where('scan_token', $job->token)->exists()) {
            return;
        }

        $lock = new UniqueLock($this->cache);

        if (! $lock->acquire($job)) {
            $job->failed(new \RuntimeException('A scan is already finishing for this folder. Try again shortly.'));

            return;
        }

        try {
            $this->bus->dispatch($job);
        } catch (Throwable $exception) {
            $lock->release($job);
            $job->failed($exception);
            report($exception);
        }
    }
}
