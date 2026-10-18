<?php

namespace Tests\Feature\Models;

use App\Models\Vault;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VaultTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_each_vault_gets_its_own_bitwarden_device_id_and_keeps_an_integer_key(): void
    {
        [$first, $second] = Vault::factory()->count(2)->create();

        $this->assertTrue(Str::isUuid($first->bitwarden_device_id));
        $this->assertTrue(Str::isUuid($second->bitwarden_device_id));
        $this->assertNotSame($first->bitwarden_device_id, $second->bitwarden_device_id);
        $this->assertSame($first->id + 1, $second->id);
        $this->assertSame($first->bitwarden_device_id, $first->refresh()->bitwarden_device_id);
    }
}
