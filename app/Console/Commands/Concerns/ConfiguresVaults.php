<?php

namespace App\Console\Commands\Concerns;

use App\Enums\BackupFormat;
use App\Enums\BackupFrequency;
use Closure;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Prompts, validation and environment variables shared by the commands that
 * create and edit vaults.
 */
trait ConfiguresVaults
{
    use ValidatesInput;

    /**
     * The server URL of Bitwarden's US cloud.
     */
    private const string US_SERVER_URL = 'https://vault.bitwarden.com';

    /**
     * The server URL of Bitwarden's EU cloud.
     */
    private const string EU_SERVER_URL = 'https://vault.bitwarden.eu';

    /**
     * The environment variable each secret can be read from.
     *
     * @var array<string, string>
     */
    private const array SECRET_VARIABLES = [
        'client_id' => 'VAULT_CLIENT_ID',
        'client_secret' => 'VAULT_CLIENT_SECRET',
        'master_password' => 'VAULT_MASTER_PASSWORD',
        'json_export_password' => 'VAULT_JSON_EXPORT_PASSWORD',
        'encrypted_json_export_password' => 'VAULT_ENCRYPTED_JSON_EXPORT_PASSWORD',
    ];

    /**
     * Get the secrets that are set in the environment, keyed by setting.
     *
     * @return array<string, string>
     */
    protected function secretsFromEnvironment(): array
    {
        return array_filter(array_map($this->secretFromEnvironment(...), self::SECRET_VARIABLES));
    }

    /**
     * Ask which server the account is on, returning its URL.
     */
    protected function promptForServerUrl(?string $current = null): string
    {
        $server = select(
            label: 'Which Bitwarden server is the account on?',
            options: [
                'us' => 'Bitwarden cloud, US (vault.bitwarden.com)',
                'eu' => 'Bitwarden cloud, EU (vault.bitwarden.eu)',
                'self-hosted' => 'Self-hosted',
            ],
            default: match ($current) {
                null, self::US_SERVER_URL => 'us',
                self::EU_SERVER_URL => 'eu',
                default => 'self-hosted',
            },
        );

        return match ($server) {
            'us' => self::US_SERVER_URL,
            'eu' => self::EU_SERVER_URL,
            'self-hosted' => text(
                label: 'What is the server URL?',
                placeholder: 'https://vault.example.com',
                default: in_array($current, [self::US_SERVER_URL, self::EU_SERVER_URL], true) ? '' : (string) $current,
                required: true,
                validate: ['server_url' => $this->vaultRules()['server_url']],
            ),
        };
    }

    /**
     * Ask for the account's API key and master password, skipping any that
     * are already set.
     *
     * @param  array<string, string>  $alreadySet
     * @return array<string, string>
     */
    protected function promptForCredentials(array $alreadySet = []): array
    {
        $hint = 'In the Bitwarden web vault, under Settings › Security › Keys › View API key.';

        $prompts = [
            'client_id' => fn (): string => password(
                label: 'API key client_id',
                required: true,
                validate: ['client_id' => $this->vaultRules()['client_id']],
                hint: $hint,
            ),
            'client_secret' => fn (): string => password(label: 'API key client_secret', required: true, hint: $hint),
            'master_password' => fn (): string => password(label: 'Master password', required: true),
        ];

        return array_map(fn (Closure $prompt): string => $prompt(), array_diff_key($prompts, $alreadySet));
    }

    /**
     * Ask which formats to back up in.
     *
     * @param  list<string>  $current
     * @return list<string>
     */
    protected function promptForFormats(array $current = []): array
    {
        return array_values(multiselect(
            label: 'Which formats should backups be saved in?',
            options: [
                BackupFormat::EncryptedJson->value => 'Encrypted JSON: password protected, importable into Bitwarden',
                BackupFormat::Json->value => 'JSON: unencrypted, optionally in a password-protected ZIP',
            ],
            default: $current ?: [BackupFormat::EncryptedJson->value],
            required: 'Choose at least one format.',
        ));
    }

    /**
     * Ask for the password the export in the given format is protected with.
     *
     * An encrypted JSON export always needs one. A JSON export is only zipped
     * when one is given. Passwords are entered twice, as a typo would leave
     * backups that cannot be opened.
     */
    protected function promptForExportPassword(BackupFormat $format): ?string
    {
        $exportPassword = match ($format) {
            BackupFormat::EncryptedJson => password(
                label: 'Encrypted JSON export password',
                required: true,
                hint: 'Needed to import the backup. Keep a copy outside this vault.',
            ),
            BackupFormat::Json => password(
                label: 'ZIP password for JSON backups',
                hint: 'Leave blank to save plain, unencrypted JSON.',
            ),
        };

        if ($exportPassword === '') {
            return null;
        }

        password(
            label: 'Enter the password again to confirm',
            required: true,
            validate: fn (string $confirmation): ?string => $confirmation === $exportPassword ? null : 'The passwords do not match.',
        );

        return $exportPassword;
    }

    /**
     * Ask whether to replace the export password a format already has.
     */
    protected function confirmExportPasswordChange(BackupFormat $format): bool
    {
        return confirm(label: "Change the {$format->label()} export password?", default: false);
    }

    /**
     * Ask how often to back up.
     */
    protected function promptForFrequency(?BackupFrequency $current = null): string
    {
        return select(
            label: 'How often should the vault be backed up?',
            options: collect(BackupFrequency::cases())->mapWithKeys(fn (BackupFrequency $frequency): array => [$frequency->value => $frequency->label()])->all(),
            default: ($current ?? BackupFrequency::Daily)->value,
        );
    }

    /**
     * Ask how many successful backups to keep.
     */
    protected function promptForRetainBackupsCount(int $current = 30): string
    {
        return text(
            label: 'How many successful backups should be kept?',
            default: (string) $current,
            required: true,
            validate: ['retain_backups_count' => $this->vaultRules()['retain_backups_count']],
            hint: 'Older backups and their files are deleted after each successful backup.',
        );
    }

    /**
     * Get the rules for a vault's settings.
     *
     * Formats may be empty so a vault that was paused by having no formats can
     * still be edited, but each command asks for at least one when prompting.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function vaultRules(array $input = []): array
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
     * Get the messages for missing secrets, which say where to provide them
     * when the command is not prompting.
     *
     * @return array<string, string>
     */
    protected function vaultMessages(): array
    {
        return [
            'server_url.required' => 'The server URL is required. Pass it with --server-url, such as '.self::US_SERVER_URL.' for the US cloud or '.self::EU_SERVER_URL.' for the EU cloud.',
            'client_id.required' => 'The API key client_id is required. Set it in the VAULT_CLIENT_ID environment variable.',
            'client_id.starts_with' => 'The API key client_id must start with "user.". Make sure it is the client_id, not the client_secret.',
            'client_secret.required' => 'The API key client_secret is required. Set it in the VAULT_CLIENT_SECRET environment variable.',
            'master_password.required' => 'The master password is required. Set it in the VAULT_MASTER_PASSWORD environment variable.',
            'encrypted_json_export_password.required' => 'Encrypted JSON backups need an export password. Set it in the VAULT_ENCRYPTED_JSON_EXPORT_PASSWORD environment variable.',
        ];
    }

    /**
     * Get the names used for vault settings in validation messages.
     *
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'server_url' => 'server URL',
            'formats' => 'formats',
            'formats.*' => 'format',
            'retain_backups_count' => 'number of backups to keep',
        ];
    }

    /**
     * Turn validated settings into the vault's attributes, clearing the
     * password of any format that is not enabled.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function vaultAttributes(array $validated): array
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
}
