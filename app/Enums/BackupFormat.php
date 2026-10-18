<?php

namespace App\Enums;

/**
 * The export formats a vault can be backed up in. Values match the Bitwarden CLI's "--format" option.
 */
enum BackupFormat: string
{
    /**
     * Unencrypted Bitwarden JSON, importable by other password managers.
     * Optionally stored inside an AES-256 encrypted ZIP by this application.
     */
    case Json = 'json';

    /**
     * Bitwarden's password-protected encrypted JSON, importable into any Bitwarden account.
     */
    case EncryptedJson = 'encrypted_json';

    /**
     * Get the format's human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Json => 'JSON',
            self::EncryptedJson => 'Encrypted JSON',
        };
    }
}
