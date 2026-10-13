<?php

namespace App\Services;

use App\Enums\BackupFormat;

/**
 * A request for the Bitwarden CLI to export a vault in one format to a file.
 */
final readonly class VaultExport
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public BackupFormat $format,
        public string $path,
    ) {}
}
