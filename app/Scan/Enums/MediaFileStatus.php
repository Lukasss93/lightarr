<?php

namespace App\Scan\Enums;

enum MediaFileStatus: string
{
    case Present = 'present';
    case Missing = 'missing';
    case Excluded = 'excluded';
}
