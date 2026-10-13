<?php

namespace App\Scan\Actions;

use App\Models\Library;
use Illuminate\Database\Eloquent\Collection;

class RequestDueLibraryScans
{
    public function __construct(private RequestLibraryScan $requestScan) {}

    public function handle(): int
    {
        $queued = 0;

        Library::query()->where('automatic_scanning', true)
            ->chunkById(100, function (Collection $libraries) use (&$queued): void {
                foreach ($libraries as $library) {
                    if ($library->last_scan_requested_at === null
                        || $library->last_scan_requested_at->addMinutes($library->scan_interval_minutes)->lessThanOrEqualTo(now())) {
                        $queued += $this->requestScan->handle($library);
                    }
                }
            });

        return $queued;
    }
}
