<?php

namespace App\Models;

use App\Enums\BackupStatus;
use Database\Factories\BackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['status', 'error', 'started_at', 'completed_at'])]
class Backup extends Model
{
    /** @use HasFactory<BackupFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BackupStatus::class,
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the vault the backup was taken of.
     *
     * @return BelongsTo<Vault, $this>
     */
    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    /**
     * Get the exported files the backup produced, one per format.
     *
     * @return HasMany<BackupFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(BackupFile::class);
    }
}
