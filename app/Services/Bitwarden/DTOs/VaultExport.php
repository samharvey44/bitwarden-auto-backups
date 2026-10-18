<?php

namespace App\Services\Bitwarden\DTOs;

use App\Enums\BackupFormat;
use SensitiveParameter;

/**
 * Data transfer object for a vault exported by the Bitwarden CLI in one format, held in memory.
 */
final readonly class VaultExport
{
    /**
     * Create a new class instance.
     */
    public function __construct(
        public BackupFormat $format,
        #[SensitiveParameter] public string $contents,
    ) {}
}
