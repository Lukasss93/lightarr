<?php

namespace App\Models;

use App\Scan\Enums\MediaFileStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MediaFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property MediaFileStatus $status
 * @property CarbonImmutable $filesystem_modified_at
 * @property CarbonImmutable $first_seen_at
 * @property CarbonImmutable $last_seen_at
 */
#[Fillable(['library_path_id', 'relative_path', 'path_hash', 'extension', 'size_bytes', 'filesystem_modified_at', 'first_seen_at', 'last_seen_at', 'last_seen_scan_token', 'status'])]
class MediaFile extends Model
{
    /** @use HasFactory<MediaFileFactory> */
    use HasFactory;

    /** @return BelongsTo<LibraryPath, $this> */
    public function libraryPath(): BelongsTo
    {
        return $this->belongsTo(LibraryPath::class);
    }

    public function formattedSize(): string
    {
        return number_format($this->size_bytes / 1073741824, 2).' GiB';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'status' => MediaFileStatus::class,
            'filesystem_modified_at' => 'immutable_datetime',
            'first_seen_at' => 'immutable_datetime',
            'last_seen_at' => 'immutable_datetime',
        ];
    }
}
