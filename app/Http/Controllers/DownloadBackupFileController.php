<?php

namespace App\Http\Controllers;

use App\Http\Requests\DownloadBackupFileRequest;
use App\Models\BackupFile;
use App\Models\Vault;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadBackupFileController extends Controller
{
    /**
     * Download a backup file, named after its vault.
     */
    public function __invoke(DownloadBackupFileRequest $request, Vault $vault, BackupFile $backupFile): StreamedResponse
    {
        $disk = Storage::disk($backupFile->disk);

        abort_unless($disk->exists($backupFile->path), 404);

        return $disk->download($backupFile->path, Str::slug($vault->name).'-'.basename($backupFile->path));
    }
}
