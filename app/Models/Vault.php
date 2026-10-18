<?php

namespace App\Models;

use App\Enums\BackupFormat;
use App\Enums\BackupFrequency;
use App\Enums\BackupStatus;
use App\Jobs\BackupVault;
use Database\Factories\VaultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

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
#[Hidden(['bitwarden_device_id', 'client_id', 'client_secret', 'master_password', 'json_export_password', 'encrypted_json_export_password'])]
class Vault extends Model
{
    /** @use HasFactory<VaultFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the columns that receive a unique ID when the vault is created.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['bitwarden_device_id'];
    }

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
     * Get the vault's most recent backup, whatever its status.
     *
     * @return HasOne<Backup, $this>
     */
    public function latestBackup(): HasOne
    {
        return $this->hasOne(Backup::class)->latestOfMany();
    }

    /**
     * Get the files produced by the vault's backups.
     *
     * @return HasManyThrough<BackupFile, Backup, $this>
     */
    public function backupFiles(): HasManyThrough
    {
        return $this->hasManyThrough(BackupFile::class, Backup::class);
    }

    /**
     * Queue a backup of the vault now.
     */
    public function queueBackup(): Backup
    {
        $backup = $this->backups()->create(['status' => BackupStatus::Pending]);

        BackupVault::dispatch($backup);

        return $backup;
    }

    /**
     * Determine whether a backup of the vault is queued or running.
     */
    public function hasBackupInProgress(): bool
    {
        return $this->backups()->whereIn('status', BackupStatus::inProgress())->exists();
    }

    /**
     * Delete the vault and its backup history, along with its backup files
     * unless they should be kept.
     *
     * The files are only removed once the vault is gone, so a failed delete
     * never leaves history pointing at missing files. Kept files lose the
     * passwords stored for them.
     */
    public function deleteWithBackups(bool $keepFiles = false): void
    {
        $files = $this->backupFiles()->get();

        $this->delete();

        if (! $keepFiles) {
            $files->each(fn (BackupFile $file) => Storage::disk($file->disk)->delete($file->path));
        }
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
