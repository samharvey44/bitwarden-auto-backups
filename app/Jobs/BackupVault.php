<?php

namespace App\Jobs;

use App\Enums\BackupFormat;
use App\Enums\BackupStatus;
use App\Events\BackupSucceeded;
use App\Models\Backup;
use App\Models\Vault;
use App\Services\BitwardenCli;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\File as HttpFile;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

#[Tries(3)]
#[Backoff([60, 600])]
#[Timeout(360)]
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

        File::ensureDirectoryExists(config('backups.working_directory'), 0700);

        $exports = collect($formats)
            ->mapWithKeys(fn (BackupFormat $format): array => [
                $format->value => config('backups.working_directory').'/'.Str::uuid().'.json',
            ])
            ->all();
        $temporaryFiles = array_values($exports);
        $storedFiles = [];

        try {
            $bitwarden->exportVault($vault, $exports);

            foreach ($formats as $format) {
                $localPath = $exports[$format->value];
                $password = $vault->exportPassword($format);

                if ($format === BackupFormat::Json && filled($password)) {
                    $localPath = $temporaryFiles[] = $this->zip($localPath, $password);
                }

                $storedFiles[] = [
                    'format' => $format,
                    'disk' => config('backups.disk'),
                    'path' => $this->store($vault, $format, $localPath),
                    'size_bytes' => File::size($localPath),
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
        } finally {
            File::delete($temporaryFiles);
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
     * Put the unencrypted export inside an AES-256 encrypted ZIP and return the ZIP's path.
     */
    private function zip(string $jsonPath, string $password): string
    {
        $zipPath = Str::replaceLast('.json', '.zip', $jsonPath);
        $zip = new ZipArchive;

        throw_unless($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) === true, RuntimeException::class, 'Unable to create the ZIP archive.');

        $zip->addFile($jsonPath, 'vault.json');
        $zip->setEncryptionName('vault.json', ZipArchive::EM_AES_256, $password);

        throw_unless($zip->close(), RuntimeException::class, 'Unable to write the ZIP archive.');

        return $zipPath;
    }

    /**
     * Copy a finished export onto the backup disk and return its path there.
     */
    private function store(Vault $vault, BackupFormat $format, string $localPath): string
    {
        $disk = config('backups.disk');
        $extension = match ($format) {
            BackupFormat::Json => pathinfo($localPath, PATHINFO_EXTENSION),
            BackupFormat::EncryptedJson => 'encrypted.json',
        };
        $filename = now()->format('Y-m-d_His')."-{$this->backup->id}.{$extension}";

        $path = Storage::disk($disk)->putFileAs("backups/{$vault->id}", new HttpFile($localPath), $filename);

        throw_if($path === false, RuntimeException::class, "Unable to write the export to the [{$disk}] disk.");

        return $path;
    }
}
