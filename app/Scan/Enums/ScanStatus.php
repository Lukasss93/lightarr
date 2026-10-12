<?php

namespace App\Scan\Enums;

enum ScanStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Scanning = 'scanning';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isActive(): bool
    {
        return $this === self::Queued || $this === self::Scanning;
    }
}
