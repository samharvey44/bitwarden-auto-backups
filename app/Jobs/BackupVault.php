<?php

namespace App\Jobs;

use App\Enums\BackupFormat;
use App\Enums\BackupStatus;
use App\Events\BackupSucceeded;
use App\Models\Backup;
use App\Models\Vault;
use App\Services\Bitwarden\BitwardenCli;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SensitiveParameter;
use Throwable;
use ZipArchive;

#[Tries(3)]
#[Backoff([60, 600])]
#[Timeout(360)]
#[DeleteWhenMissingModels]
class BackupVault implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Backup $backup)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(BitwardenCli $bitwarden): void
    {
        $vault = $this->backup->vault;
        $formats = $vault->enabledFormats();

        if ($formats === []) {
            $this->markFailed('The vault has no backup formats enabled.');

            return;
        }

        if ($vault->encrypted_json_export_enabled && blank($vault->encrypted_json_export_password)) {
            $this->markFailed('Encrypted JSON backups are enabled but no export password is set.');

            return;
        }

        $this->backup->update([
            'status' => BackupStatus::Running,
            'started_at' => now(),
        ]);

        $storedFiles = [];

        try {
            foreach ($bitwarden->exportVault($vault, ...$formats) as $export) {
                $password = $vault->exportPassword($export->format);

                [$contents, $extension] = match (true) {
                    $export->format === BackupFormat::EncryptedJson => [$export->contents, 'encrypted.json'],
                    filled($password) => [$this->zip($export->contents, $password), 'zip'],
                    default => [$export->contents, 'json'],
                };

                $storedFiles[] = [
                    'format' => $export->format,
                    'disk' => config('backups.disk'),
                    'path' => $this->store($vault, $extension, $contents),
                    'size_bytes' => strlen($contents),
                    'password' => $password,
                ];
            }

            DB::transaction(function () use ($vault, $storedFiles): void {
                $this->backup->files()->createMany($storedFiles);

                $this->backup->update([
                    'status' => BackupStatus::Succeeded,
                    'error' => null,
                    'completed_at' => now(),
                ]);

                $vault->update(['last_backed_up_at' => now()]);
            });
        } catch (Throwable $exception) {
            foreach ($storedFiles as $storedFile) {
                Storage::disk($storedFile['disk'])->delete($storedFile['path']);
            }

            throw $exception;
        }

        BackupSucceeded::dispatch($this->backup);
    }

    /**
     * Handle a job failure once every attempt has been used.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage());
    }

    /**
     * Record the backup as failed for the given reason.
     */
    private function markFailed(?string $reason): void
    {
        $this->backup->update([
            'status' => BackupStatus::Failed,
            'error' => $reason,
            'completed_at' => now(),
        ]);
    }

    /**
     * Put the unencrypted export inside an AES-256 encrypted ZIP and return the archive's contents.
     *
     * The export is added from memory, so only the encrypted archive is ever
     * written to the working disk, and it is removed before returning.
     */
    private function zip(#[SensitiveParameter] string $json, #[SensitiveParameter] string $password): string
    {
        $workingDisk = Storage::disk(config('backups.working_disk'));
        $zipFilename = Str::uuid().'.zip';
        $zip = new ZipArchive;

        try {
            throw_unless($zip->open($workingDisk->path($zipFilename), ZipArchive::CREATE | ZipArchive::EXCL) === true, RuntimeException::class, 'Unable to create the ZIP archive.');

            $zip->addFromString('vault.json', $json);
            $zip->setEncryptionName('vault.json', ZipArchive::EM_AES_256, $password);

            throw_unless($zip->close(), RuntimeException::class, 'Unable to write the ZIP archive.');

            return $workingDisk->get($zipFilename);
        } finally {
            $workingDisk->delete($zipFilename);
        }
    }

    /**
     * Write a finished export to the backup disk and return its path there.
     */
    private function store(Vault $vault, string $extension, #[SensitiveParameter] string $contents): string
    {
        $disk = config('backups.disk');
        $path = "backups/{$vault->id}/".now()->format('Y-m-d_His')."-{$this->backup->id}.{$extension}";

        throw_unless(Storage::disk($disk)->put($path, $contents), RuntimeException::class, "Unable to write the export to the [{$disk}] disk.");

        return $path;
    }
}
