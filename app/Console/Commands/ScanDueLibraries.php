<?php

namespace App\Console\Commands;

use App\Scan\Actions\RequestDueLibraryScans;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('libraries:scan-due')]
#[Description('Queue scans for libraries whose configured interval has elapsed')]
class ScanDueLibraries extends Command
{
    public function handle(RequestDueLibraryScans $requestScans): int
    {
        $this->info('Queued '.$requestScans->handle().' folder scans.');

        return self::SUCCESS;
    }
}
