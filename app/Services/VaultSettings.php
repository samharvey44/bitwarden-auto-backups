<?php

namespace App\Services;

use App\Enums\BackupFormat;
use App\Enums\BackupFrequency;
use App\Models\Vault;
use Illuminate\Validation\Rule;

/**
 * The validation and mapping of a vault's settings, shared by the commands and
 * web forms that create and edit vaults so they can never drift apart.
 *
 * Settings are validated in one flat shape, with the enabled formats as a list
 * of their values, then turned into the vault's attributes.
 */
final class VaultSettings
{
    /**
     * The server URL of Bitwarden's US cloud.
     */
    public const string US_SERVER_URL = 'https://vault.bitwarden.com';

    /**
     * The server URL of Bitwarden's EU cloud.
     */
    public const string EU_SERVER_URL = 'https://vault.bitwarden.eu';

    /**
     * Describe the server at the given URL, naming Bitwarden's clouds.
     */
    public static function serverLabel(string $serverUrl): string
    {
        return match ($serverUrl) {
            self::US_SERVER_URL => 'Bitwarden cloud (US)',
            self::EU_SERVER_URL => 'Bitwarden cloud (EU)',
            default => parse_url($serverUrl, PHP_URL_HOST) ?: $serverUrl,
        };
    }

    /**
     * Get the rules for a vault's settings.
     *
     * Formats may be empty so a vault that was paused by having no formats can
     * still be edited, but each form asks for at least one.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, list<mixed>>
     */
    public static function rules(array $input = []): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'server_url' => ['required', 'string', 'url:http,https', 'max:255'],
            'client_id' => ['required', 'string', 'starts_with:user.'],
            'client_secret' => ['required', 'string'],
            'master_password' => ['required', 'string'],
            'formats' => ['present', 'array'],
            'formats.*' => ['distinct', Rule::enum(BackupFormat::class)],
            'json_export_password' => ['nullable', 'string'],
            'encrypted_json_export_password' => [
                Rule::requiredIf(in_array(BackupFormat::EncryptedJson->value, (array) ($input['formats'] ?? []), true)),
                'nullable',
                'string',
            ],
            'frequency' => ['required', Rule::enum(BackupFrequency::class)],
            'retain_backups_count' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * Get the validation messages that apply wherever settings are entered.
     *
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'client_id.starts_with' => 'The API key client_id must start with "user.". Make sure it is the client_id, not the client_secret.',
        ];
    }

    /**
     * Get the names used for the settings in validation messages.
     *
     * @return array<string, string>
     */
    public static function attributeNames(): array
    {
        return [
            'server_url' => 'server URL',
            'client_id' => 'API key client_id',
            'client_secret' => 'API key client_secret',
            'master_password' => 'master password',
            'formats' => 'formats',
            'formats.*' => 'format',
            'json_export_password' => 'ZIP password',
            'encrypted_json_export_password' => 'encrypted JSON export password',
            'retain_backups_count' => 'number of backups to keep',
        ];
    }

    /**
     * Get the vault's current settings, in the shape they are validated in.
     *
     * @return array<string, mixed>
     */
    public static function fromVault(Vault $vault): array
    {
        return [
            'name' => $vault->name,
            'server_url' => $vault->server_url,
            'client_id' => $vault->client_id,
            'client_secret' => $vault->client_secret,
            'master_password' => $vault->master_password,
            'formats' => array_map(fn (BackupFormat $format): string => $format->value, $vault->enabledFormats()),
            'json_export_password' => $vault->json_export_password,
            'encrypted_json_export_password' => $vault->encrypted_json_export_password,
            'frequency' => $vault->frequency->value,
            'retain_backups_count' => $vault->retain_backups_count,
        ];
    }

    /**
     * Turn validated settings into the vault's attributes, clearing the
     * password of any format that is not enabled.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public static function attributes(array $validated): array
    {
        $formats = array_map(BackupFormat::from(...), $validated['formats']);
        $jsonEnabled = in_array(BackupFormat::Json, $formats, true);
        $encryptedJsonEnabled = in_array(BackupFormat::EncryptedJson, $formats, true);

        return [
            'name' => $validated['name'],
            'server_url' => $validated['server_url'],
            'client_id' => $validated['client_id'],
            'client_secret' => $validated['client_secret'],
            'master_password' => $validated['master_password'],
            'json_export_enabled' => $jsonEnabled,
            'json_export_password' => $jsonEnabled && filled($validated['json_export_password'] ?? null) ? $validated['json_export_password'] : null,
            'encrypted_json_export_enabled' => $encryptedJsonEnabled,
            'encrypted_json_export_password' => $encryptedJsonEnabled ? $validated['encrypted_json_export_password'] : null,
            'frequency' => BackupFrequency::from($validated['frequency']),
            'retain_backups_count' => (int) $validated['retain_backups_count'],
        ];
    }

    /**
     * Fill the vault with validated settings, without saving it.
     *
     * Changing the frequency reschedules the next backup from the last one, so
     * it takes effect straight away rather than after the old interval.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fill(Vault $vault, array $validated): Vault
    {
        $vault->fill(self::attributes($validated));

        if ($vault->isDirty('frequency')) {
            $vault->next_backup_at = $vault->last_backed_up_at === null ? null : $vault->frequency->nextRunAfter($vault->last_backed_up_at);
        }

        return $vault;
    }
}
