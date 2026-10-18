<?php

namespace App\Http\Resources;

use App\Enums\BackupFormat;
use App\Models\BackupFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A backup file as shown in the browser.
 *
 * The password it was made with is never included, only whether it is
 * encrypted.
 *
 * @mixin BackupFile
 */
class BackupFileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'format' => $this->format->value,
            'format_label' => $this->format->label(),
            'size_bytes' => $this->size_bytes,
            'is_encrypted' => $this->format === BackupFormat::EncryptedJson || filled($this->password),
            'download_url' => route('vaults.backup-files.download', ['vault' => $this->backup->vault_id, 'backupFile' => $this->id]),
        ];
    }
}
