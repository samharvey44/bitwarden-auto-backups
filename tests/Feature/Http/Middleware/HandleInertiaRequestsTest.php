<?php

namespace Tests\Feature\Http\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HandleInertiaRequestsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_shares_only_the_users_id_name_and_email(): void
    {
        $user = User::factory()->withTwoFactorAuthentication()->create(['name' => 'Sam', 'email' => 'sam@example.com']);

        $this->actingAs($user)
            ->get('/vaults')
            ->assertInertia(fn (Assert $page) => $page
                ->component('vaults/Index')
                ->where('auth.user', ['id' => $user->id, 'name' => 'Sam', 'email' => 'sam@example.com'])
                ->where('appName', config('app.name')));
    }

    public function test_shares_no_user_with_guests(): void
    {
        $this->get('/login')->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }
}
