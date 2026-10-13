<?php

namespace App\Models;

use App\Enums\BackupFormat;
use App\Enums\BackupFrequency;
use Database\Factories\VaultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'server_url',
    'client_id',
    'client_secret',
    'master_password',
    'json_export_enabled',
    'json_export_password',
    'encrypted_json_export_enabled',
    'encrypted_json_export_password',
    'frequency',
    'retain_backups_count',
    'next_backup_at',
    'last_backed_up_at',
])]
#[Hidden(['client_id', 'client_secret', 'master_password', 'json_export_password', 'encrypted_json_export_password'])]
class Vault extends Model
{
    /** @use HasFactory<VaultFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_id' => 'encrypted',
            'client_secret' => 'encrypted',
            'master_password' => 'encrypted',
            'json_export_enabled' => 'boolean',
            'json_export_password' => 'encrypted',
            'encrypted_json_export_enabled' => 'boolean',
            'encrypted_json_export_password' => 'encrypted',
            'frequency' => BackupFrequency::class,
            'retain_backups_count' => 'integer',
            'next_backup_at' => 'immutable_datetime',
            'last_backed_up_at' => 'immutable_datetime',
        ];
    }

    /**
     * Get the user that owns the vault.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the backups taken of the vault.
     *
     * @return HasMany<Backup, $this>
     */
    public function backups(): HasMany
    {
        return $this->hasMany(Backup::class);
    }

    /**
     * Get the export formats the vault is backed up in.
     *
     * @return list<BackupFormat>
     */
    public function enabledFormats(): array
    {
        return array_values(array_filter([
            $this->json_export_enabled ? BackupFormat::Json : null,
            $this->encrypted_json_export_enabled ? BackupFormat::EncryptedJson : null,
        ]));
    }

    /**
     * Get the password a backup in the given format is protected with, if any.
     */
    public function exportPassword(BackupFormat $format): ?string
    {
        return match ($format) {
            BackupFormat::Json => $this->json_export_password,
            BackupFormat::EncryptedJson => $this->encrypted_json_export_password,
        };
    }

    /**
     * Scope the query to vaults with at least one export format enabled whose
     * next backup is due.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function dueForBackup(Builder $query): void
    {
        $query
            ->where(function (Builder $query): void {
                $query->where('json_export_enabled', true)->orWhere('encrypted_json_export_enabled', true);
            })
            ->where(function (Builder $query): void {
                $query->whereNull('next_backup_at')->orWhere('next_backup_at', '<=', now());
            });
    }
}
