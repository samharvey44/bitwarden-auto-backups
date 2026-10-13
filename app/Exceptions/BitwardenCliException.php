<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Str;

class BitwardenCliException extends Exception
{
    /**
     * Create an exception for a CLI command that exited unsuccessfully.
     *
     * Only the command name is included, never its arguments, because the
     * arguments can contain the export password.
     */
    public static function commandFailed(string $command, ProcessResult $result): self
    {
        $output = self::withoutDataDirectoryNotices($result->errorOutput())
            ?: self::withoutDataDirectoryNotices($result->output());

        return new self(sprintf(
            'Bitwarden CLI command [%s] failed with exit code %d: %s',
            $command,
            $result->exitCode(),
            Str::limit($output ?: 'no output', 500),
        ));
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
