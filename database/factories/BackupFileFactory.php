<?php

namespace Database\Factories;

use App\Enums\BackupFormat;
use App\Models\Backup;
use App\Models\BackupFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BackupFile>
 */
class BackupFileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'backup_id' => Backup::factory()->succeeded(),
            'format' => BackupFormat::EncryptedJson,
            'disk' => 'local',
            'path' => 'backups/'.fake()->uuid().'/vault.encrypted.json',
            'size_bytes' => fake()->numberBetween(1_000, 100_000),
            'password' => fake()->password(16),
        ];
    }

    /**
     * Indicate that the file is an unencrypted JSON export, stored as a plain
     * JSON file, or inside a ZIP protected with the given password.
     */
    public function json(?string $zipPassword = null): static
    {
        return $this->state(fn (array $attributes) => [
            'format' => BackupFormat::Json,
            'path' => 'backups/'.fake()->uuid().(is_null($zipPassword) ? '/vault.json' : '/vault.zip'),
            'password' => $zipPassword,
        ]);
    }
}
