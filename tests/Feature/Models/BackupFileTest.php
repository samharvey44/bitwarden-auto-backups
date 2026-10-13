<?php

namespace Tests\Feature\Models;

use App\Enums\BackupFormat;
use App\Models\Backup;
use App\Models\BackupFile;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BackupFileTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_rejects_a_second_file_in_the_same_format_for_one_backup(): void
    {
        $backup = Backup::factory()->succeeded()->create();
        BackupFile::factory()->for($backup)->create(['format' => BackupFormat::EncryptedJson]);

        $this->expectException(UniqueConstraintViolationException::class);

        BackupFile::factory()->for($backup)->create(['format' => BackupFormat::EncryptedJson]);
    }

    public function test_allows_one_file_per_format_for_one_backup(): void
    {
        $backup = Backup::factory()->succeeded()->create();

        BackupFile::factory()->for($backup)->create();
        BackupFile::factory()->for($backup)->json()->create();

        $this->assertSame(2, $backup->files()->count());
    }
}
