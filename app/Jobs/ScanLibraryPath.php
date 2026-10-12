<?php

namespace App\Jobs;

use App\Models\LibraryPath;
use App\Scan\Enums\ScanStatus;
use App\Scan\MediaScanner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ScanLibraryPath implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    /** @param list<string> $exclusions */
    public function __construct(public int $pathId, public string $token, public array $exclusions) {}

    public function uniqueId(): string
    {
        return (string) $this->pathId;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('library-path:'.$this->pathId))->dontRelease()->expireAfter(1860)];
    }

    public function handle(MediaScanner $scanner): void
    {
        $claimed = LibraryPath::query()->whereKey($this->pathId)
            ->where('scan_token', $this->token)
            ->where('scan_status', ScanStatus::Queued)
            ->update(['scan_status' => ScanStatus::Scanning, 'scan_started_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        try {
            $scanner->scan($this->pathId, $this->token, $this->exclusions);
        } catch (Throwable $exception) {
            $this->failed($exception);

            throw $exception;
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
