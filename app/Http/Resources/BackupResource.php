<?php

namespace App\Http\Resources;

use App\Models\Backup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A backup as shown in the browser, with its files.
 *
 * @mixin Backup
 */
class BackupResource extends JsonResource
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
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'error' => $this->error,
            'queued_at' => $this->created_at?->toIso8601ZuluString(),
            'started_at' => $this->started_at?->toIso8601ZuluString(),
            'completed_at' => $this->completed_at?->toIso8601ZuluString(),
            'files' => BackupFileResource::collection($this->files)->resolve(),
        ];
    }
}
