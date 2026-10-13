<?php

namespace Database\Factories;

use App\Enums\BackupStatus;
use App\Models\Backup;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vault_id' => Vault::factory(),
            'status' => BackupStatus::Pending,
        ];
    }

    /**
     * Indicate that the backup completed successfully.
     */
    public function succeeded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BackupStatus::Succeeded,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the backup failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BackupStatus::Failed,
            'error' => 'Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }
}
