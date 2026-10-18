import { formatDateTime } from './formatDateTime';

/**
 * The formats a vault can be backed up in, in the order they are offered.
 */
export const backupFormats = [
    {
        value: 'encrypted_json',
        label: 'Encrypted JSON',
        description: 'Protected by an export password and importable into any Bitwarden account.',
    },
    {
        value: 'json',
        label: 'JSON',
        description: 'Unencrypted and importable into other password managers. Saved inside an AES-256 encrypted ZIP when given a password.',
    },
];

/**
 * Describe each format the vault is backed up in.
 *
 * @param {{formats: string[], has_json_export_password: boolean}} vault
 * @returns {string[]}
 */
export function formatLabels(vault) {
    return backupFormats
        .filter((format) => vault.formats.includes(format.value))
        .map((format) => {
            if (format.value !== 'json') {
                return format.label;
            }

            return vault.has_json_export_password ? 'JSON (ZIP)' : 'JSON (unencrypted)';
        });
}

/**
 * Describe when the vault will next be backed up.
 *
 * @param {{formats: string[], next_backup_at: string|null}} vault
 * @returns {string}
 */
export function nextBackupLabel(vault) {
    if (vault.formats.length === 0) {
        return 'Paused';
    }

    if (vault.next_backup_at === null || new Date(vault.next_backup_at) <= new Date()) {
        return 'Due now';
    }

    return formatDateTime(vault.next_backup_at);
}
