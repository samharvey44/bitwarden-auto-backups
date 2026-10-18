<?php

namespace App\Services\Bitwarden\Exceptions;

use App\Enums\BackupFormat;
use Exception;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Str;

class BitwardenCliException extends Exception
{
    /**
     * Create an exception for a CLI command that exited unsuccessfully.
     *
     * Only the command name is included, never its arguments, because the
     * arguments can contain the export password. The standard output is only
     * used as a fallback when it is not sensitive, such as a vault export.
     */
    public static function commandFailed(string $command, ProcessResult $result, bool $outputIsSensitive = false): self
    {
        $output = self::withoutDataDirectoryNotices($result->errorOutput())
            ?: ($outputIsSensitive ? '' : self::withoutDataDirectoryNotices($result->output()));

        return new self(sprintf(
            'Bitwarden CLI command [%s] failed with exit code %d: %s',
            $command,
            $result->exitCode(),
            Str::limit($output ?: 'no output', 500),
        ));
    }

    /**
     * Create an exception for a CLI command that ran past its timeout.
     */
    public static function commandTimedOut(string $command): self
    {
        return new self(sprintf(
            'Bitwarden CLI command [%s] timed out after %d seconds.',
            $command,
            config('backups.cli.timeout'),
        ));
    }

    /**
     * Create an exception for an export command that succeeded without
     * writing anything to standard output.
     */
    public static function emptyExport(BackupFormat $format): self
    {
        return new self("Bitwarden CLI command [export] returned an empty {$format->value} export.");
    }

    /**
     * Remove the notices the CLI prints when it first creates its data
     * directory, which would otherwise hide the real error behind a server path.
     */
    private static function withoutDataDirectoryNotices(string $output): string
    {
        return Str::of($output)
            ->explode("\n")
            ->reject(fn (string $line): bool => Str::startsWith($line, ['Could not find dir,', 'Could not find data file,']))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->implode("\n");
    }
}
