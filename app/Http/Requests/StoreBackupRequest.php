<?php

namespace App\Http\Requests;

use App\Models\Vault;
use Closure;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class StoreBackupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->vault());
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
     * Get the checks that the vault can be backed up now.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->vault()->enabledFormats() === []) {
                    $validator->errors()->add('backup', 'Choose at least one backup format before backing up.');
                } elseif ($this->vault()->hasBackupInProgress()) {
                    $validator->errors()->add('backup', 'A backup of this vault is already queued or running.');
                }
            },
        ];
    }

    /**
     * Get the vault being backed up.
     */
    public function vault(): Vault
    {
        return $this->route('vault');
    }
}
