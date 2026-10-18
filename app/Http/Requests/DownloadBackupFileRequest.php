<?php

namespace App\Http\Requests;

use App\Models\BackupFile;
use App\Models\Vault;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DownloadBackupFileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        if ($this->backupFile()->backup->vault_id !== $this->vault()->id) {
            return Response::denyAsNotFound();
        }

        return Gate::inspect('view', $this->vault());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get the vault the file is being downloaded from.
     */
    public function vault(): Vault
    {
        return $this->route('vault');
    }

    /**
     * Get the file being downloaded.
     */
    public function backupFile(): BackupFile
    {
        return $this->route('backupFile');
    }
}
