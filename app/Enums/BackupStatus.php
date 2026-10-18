<?php

namespace App\Enums;

enum BackupStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * Get the status's human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Running => 'Running',
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
        };
    }

    /**
     * Get the statuses of backups that have not finished yet.
     *
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::Pending, self::Running];
    }
}
