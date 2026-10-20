<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

enum BackupFrequency: string
{
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    /**
     * Get the frequency's human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hourly => 'Hourly',
            self::Daily => 'Daily',
            self::Weekly => 'Weekly',
            self::Monthly => 'Monthly',
        };
    }

    /**
     * Get the time of the run that follows one at the given time.
     */
    public function nextRunAfter(CarbonInterface $time): CarbonImmutable
    {
        $time = CarbonImmutable::instance($time);

        return match ($this) {
            self::Hourly => $time->addHour(),
            self::Daily => $time->addDay(),
            self::Weekly => $time->addWeek(),
            self::Monthly => $time->addMonthNoOverflow(),
        };
    }
}
