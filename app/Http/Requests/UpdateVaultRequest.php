<?php

namespace App\Http\Requests;

use App\Models\Vault;
use App\Services\VaultSettings;
use Illuminate\Auth\Access\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateVaultRequest extends FormRequest
{
    /**
     * The secrets the browser never sees, so leaving one blank keeps its
     * current value.
     *
     * @var list<string>
     */
    private const array SECRETS = [
        'client_id',
        'client_secret',
        'master_password',
        'json_export_password',
        'encrypted_json_export_password',
    ];

    /**
     * The secrets that were given new values in this request.
     *
     * @var list<string>
     */
    private array $changedSecrets = [];

    /**
     * The settings to validate, once worked out.
     *
     * @var array<string, mixed>|null
     */
    private ?array $settings = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->vault());
    }

    /**
     * Get the data to validate: the submitted settings, with the current value
     * of every secret left blank, so the vault's full settings are validated
     * together with the shared rules.
     *
     * Ticking "remove_json_export_password" saves JSON backups as plain JSON.
     *
     * This runs after authorization, unlike prepareForValidation(), so a
     * vault's secrets are only read once the user is known to own it.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $current = VaultSettings::fromVault($this->vault());
        $settings = $this->all();

        foreach (self::SECRETS as $secret) {
            if ($this->filled($secret)) {
                $this->changedSecrets[] = $secret;
            } else {
                $settings[$secret] = $current[$secret];
            }
        }

        if ($this->boolean('remove_json_export_password')) {
            $settings['json_export_password'] = null;
        }

        return $this->settings = $settings;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * New export passwords must be entered twice, as a typo would leave
     * backups that cannot be opened.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $rules = VaultSettings::rules($this->validationData());

        $rules['formats'][] = 'min:1';

        foreach (['json_export_password', 'encrypted_json_export_password'] as $exportPassword) {
            if (in_array($exportPassword, $this->changedSecrets, true)) {
                $rules[$exportPassword][] = 'confirmed';
            }
        }

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

    /**
     * Get the vault being updated.
     */
    public function vault(): Vault
    {
        return $this->route('vault');
    }
}
