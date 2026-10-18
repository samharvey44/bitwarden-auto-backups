<?php

namespace App\Http\Resources;

use App\Enums\BackupFormat;
use App\Models\Vault;
use App\Services\VaultSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vault as shown in the browser.
 *
 * Its API key, master password and export passwords are never included, only
 * whether each export password is set.
 *
 * @mixin Vault
 */
class VaultResource extends JsonResource
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
            'name' => $this->name,
            'server_url' => $this->server_url,
            'server_label' => VaultSettings::serverLabel($this->server_url),
            'formats' => array_map(fn (BackupFormat $format): string => $format->value, $this->enabledFormats()),
            'has_json_export_password' => filled($this->json_export_password),
            'has_encrypted_json_export_password' => filled($this->encrypted_json_export_password),
            'frequency' => $this->frequency->value,
            'frequency_label' => $this->frequency->label(),
            'retain_backups_count' => $this->retain_backups_count,
            'last_backed_up_at' => $this->last_backed_up_at?->toIso8601ZuluString(),
            'next_backup_at' => $this->next_backup_at?->toIso8601ZuluString(),
            'latest_backup' => $this->whenLoaded('latestBackup', fn (): array => [
                'status' => $this->latestBackup->status->value,
                'status_label' => $this->latestBackup->status->label(),
                'completed_at' => $this->latestBackup->completed_at?->toIso8601ZuluString(),
            ]),
        ];
    }
}
