<?php

namespace Database\Factories;

use App\Models\Library;
use App\Models\LibraryPath;
use App\Scan\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LibraryPath> */
class LibraryPathFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $path = '/media/'.fake()->uuid();

        return [
            'library_id' => Library::factory(),
            'path' => $path,
            'path_hash' => hash('sha256', $path),
            'scan_status' => ScanStatus::Idle,
        ];
    }

    public function atPath(string $path): static
    {
        return $this->state(fn (): array => ['path' => $path, 'path_hash' => hash('sha256', $path)]);
    }
}
