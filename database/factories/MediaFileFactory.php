<?php

namespace Database\Factories;

use App\Models\LibraryPath;
use App\Models\MediaFile;
use App\Scan\Enums\MediaFileStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MediaFile> */
class MediaFileFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $path = fake()->uuid().'.mkv';

        return [
            'library_path_id' => LibraryPath::factory(),
            'relative_path' => $path,
            'path_hash' => hash('sha256', $path),
            'extension' => 'mkv',
            'size_bytes' => 1073741824,
            'filesystem_modified_at' => now(),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'status' => MediaFileStatus::Present,
        ];
    }

    public function atPath(string $path): static
    {
        return $this->state(fn (): array => [
            'relative_path' => $path,
            'path_hash' => hash('sha256', $path),
            'extension' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
        ]);
    }

    public function missing(): static
    {
        return $this->state(fn (): array => ['status' => MediaFileStatus::Missing]);
    }

    public function excluded(): static
    {
        return $this->state(fn (): array => ['status' => MediaFileStatus::Excluded]);
    }
}
