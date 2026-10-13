<?php

namespace App\Scan\Actions;

use App\Jobs\ScanLibraryPath;
use App\Models\Library;
use App\Models\LibraryPath;
use App\Scan\Enums\ScanStatus;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class RequestLibraryScan
{
    public function __construct(private Dispatcher $bus) {}

    /**
     * Queue a scan for each folder of the library that does not already have one pending.
     *
     * @return int The number of folders queued.
     */
    public function handle(Library $library): int
    {
        $queued = $this->queueAll($library->paths()->get());

        if ($queued > 0) {
            $library->update(['last_scan_requested_at' => now()]);
        }

        return $queued;
    }

    /**
     * Queue a scan for each folder of the library that has never been scanned.
     *
     * This leaves the library's automatic scan timer alone, so adding a folder does not delay
     * the next scan of the others. A library that has never been scanned starts its timer
     * here, because all of its folders are new.
     *
     * @return int The number of folders queued.
     */
    public function forNewPaths(Library $library): int
    {
        $queued = $this->queueAll($library->paths()->where('scan_status', ScanStatus::Idle)->get());

        if ($queued > 0 && $library->last_scan_requested_at === null) {
            $library->update(['last_scan_requested_at' => now()]);
        }

        return $queued;
    }

    /** @param Collection<int, LibraryPath> $paths */
    private function queueAll(Collection $paths): int
    {
        return $paths->filter(fn (LibraryPath $path): bool => $this->queue($path))->count();
    }

    private function queue(LibraryPath $path): bool
    {
        if ($path->hasPendingScan()) {
            return false;
        }

        // A lost queued scan keeps its token, so if the original job does turn up,
        // it and the replacement compete for the same claim and only one of them scans.
        $token = $path->scan_status === ScanStatus::Queued && $path->scan_token !== null
            ? $path->scan_token
            : (string) Str::uuid();

        // Only update the folder if no other request or job has changed it since it was read.
        $updated = LibraryPath::query()->whereKey($path->id)
            ->where('scan_status', $path->scan_status)
            ->where('scan_token', $path->scan_token)
            ->update([
                'scan_status' => ScanStatus::Queued,
                'scan_token' => $token,
                'scan_requested_at' => now(),
                'scan_started_at' => null,
                'scan_finished_at' => null,
                'scan_error' => null,
            ]);

        if ($updated === 0) {
            return false;
        }

        $job = new ScanLibraryPath($path->id, $token);
        DB::afterCommit(fn () => $this->dispatch($job));

        return true;
    }

    private function dispatch(ScanLibraryPath $job): void
    {
        try {
            $this->bus->dispatch($job);
        } catch (Throwable $exception) {
            $job->failed($exception);
            report($exception);
        }
    }
}
