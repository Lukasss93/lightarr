<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /** @param array<string, string> $files */
    public function mediaDirectory(array $files = []): string
    {
        $directory = storage_path('framework/testing/media/'.Str::uuid());
        File::ensureDirectoryExists($directory);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));

        foreach ($files as $path => $contents) {
            File::ensureDirectoryExists(dirname($directory.'/'.$path));
            File::put($directory.'/'.$path, $contents);
        }

        return $directory;
    }
}
