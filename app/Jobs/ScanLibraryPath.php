<?php

namespace App\Jobs;

use App\Models\LibraryPath;
use App\Scan\Enums\ScanStatus;
use App\Scan\MediaScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Scans one library folder for the scan request identified by the token.
 *
 * A job whose token no longer matches the folder belongs to an older request and does nothing.
 */
class ScanLibraryPath implements ShouldQueue
{
    use Queueable;

    /**
     * The worker stops a scan after this many seconds, so a scan that has made no progress
     * for longer than this is treated as lost and the folder can be queued again.
     */
    public const int TIMEOUT_SECONDS = 1800;

    public int $timeout = self::TIMEOUT_SECONDS;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $pathId, public string $token) {}

    public function handle(MediaScanner $scanner): void
    {
        $claimed = LibraryPath::query()->whereKey($this->pathId)
            ->where('scan_token', $this->token)
            ->where('scan_status', ScanStatus::Queued)
            ->update(['scan_status' => ScanStatus::Scanning, 'scan_started_at' => now()]);

        if ($claimed === 1) {
            $scanner->scan($this->pathId, $this->token);
        }
    }

    public function failed(?Throwable $exception): void
    {
        LibraryPath::query()->whereKey($this->pathId)->where('scan_token', $this->token)
            ->whereIn('scan_status', [ScanStatus::Queued, ScanStatus::Scanning])
            ->update([
                'scan_status' => ScanStatus::Failed,
                'scan_finished_at' => now(),
                'scan_error' => $exception?->getMessage() ?? 'The scan failed.',
            ]);
    }
}
