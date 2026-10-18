<?php

namespace Database\Factories;

use App\Enums\BackupFrequency;
use App\Models\User;
use App\Models\Vault;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vault>
 */
class VaultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * By default the vault is backed up in Bitwarden's encrypted JSON format only.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'server_url' => 'https://vault.bitwarden.com',
            'client_id' => 'user.'.fake()->uuid(),
            'client_secret' => fake()->regexify('[A-Za-z0-9]{30}'),
            'master_password' => fake()->password(16),
            'json_export_enabled' => false,
            'json_export_password' => null,
            'encrypted_json_export_enabled' => true,
            'encrypted_json_export_password' => fake()->password(16),
            'frequency' => BackupFrequency::Daily,
            'retain_backups_count' => 30,
            'next_backup_at' => null,
            'last_backed_up_at' => null,
        ];
    }

    /**
     * Indicate that the vault's account is on a server other than Bitwarden's
     * US cloud, such as the EU cloud or a self-hosted server.
     */
    public function withServerUrl(string $serverUrl): static
    {
        return $this->state(fn (array $attributes) => [
            'server_url' => $serverUrl,
        ]);
    }

    /**
     * Indicate that the vault is also backed up as unencrypted JSON, zipped
     * with the given password when one is provided.
     */
    public function withJsonExport(?string $password = null): static
    {
        return $this->state(fn (array $attributes) => [
            'json_export_enabled' => true,
            'json_export_password' => $password,
        ]);
    }

    /**
     * Indicate that the vault is not backed up in Bitwarden's encrypted JSON format.
     */
    public function withoutEncryptedJsonExport(): static
    {
        return $this->state(fn (array $attributes) => [
            'encrypted_json_export_enabled' => false,
            'encrypted_json_export_password' => null,
        ]);
    }

    /**
     * Indicate that no export formats are enabled, so the vault is never backed up.
     */
    public function withoutExports(): static
    {
        return $this->withoutEncryptedJsonExport()->state(fn (array $attributes) => [
            'json_export_enabled' => false,
            'json_export_password' => null,
        ]);
    }
}
