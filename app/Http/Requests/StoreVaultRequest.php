<?php

namespace App\Http\Requests;

use App\Services\VaultSettings;
use Illuminate\Foundation\Http\FormRequest;

class StoreVaultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Any signed-in user may add vaults of their own.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Export passwords must be entered twice, as a typo would leave backups
     * that cannot be opened.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = VaultSettings::rules($this->all());

        $rules['formats'][] = 'min:1';
        $rules['encrypted_json_export_password'][] = 'confirmed';
        $rules['json_export_password'][] = 'confirmed';

        return $rules;
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...VaultSettings::messages(),
            'formats.min' => 'Choose at least one format to back up in.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return VaultSettings::attributeNames();
    }
}
