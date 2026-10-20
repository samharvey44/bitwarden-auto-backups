<?php

namespace Tests\Unit\Enums;

use App\Enums\BackupFrequency;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BackupFrequencyTest extends TestCase
{
    #[DataProvider('nextRunTimesDataProvider')]
    public function test_returns_the_next_run_one_period_later(BackupFrequency $frequency, string $expected): void
    {
        $lastRun = CarbonImmutable::parse('2026-01-31 10:30:00');

        $nextRun = $frequency->nextRunAfter($lastRun);

        $this->assertSame($expected, $nextRun->toDateTimeString());
    }

    /**
     * @return array<string, array{BackupFrequency, string}>
     */
    public static function nextRunTimesDataProvider(): array
    {
        return [
            'hourly runs an hour later' => [BackupFrequency::Hourly, '2026-01-31 11:30:00'],
            'daily runs the next day' => [BackupFrequency::Daily, '2026-02-01 10:30:00'],
            'weekly runs seven days later' => [BackupFrequency::Weekly, '2026-02-07 10:30:00'],
            'monthly clamps to the end of a shorter month' => [BackupFrequency::Monthly, '2026-02-28 10:30:00'],
        ];
    }
}
