<?php

namespace Tests\Feature\Listeners;

use App\Events\BackupSucceeded;
use App\Listeners\SendBackupHeartbeat;
use App\Models\Backup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendBackupHeartbeatTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string HEARTBEAT_URL = 'https://heartbeat.example.com/backups';

    public function test_listens_for_successful_backups(): void
    {
        Event::fake();

        Event::assertListening(BackupSucceeded::class, SendBackupHeartbeat::class);
    }

    public function test_pings_the_heartbeat_url(): void
    {
        config(['backups.heartbeat_url' => self::HEARTBEAT_URL]);
        Http::preventStrayRequests();
        Http::fake([self::HEARTBEAT_URL => Http::response('OK')]);
        $backup = Backup::factory()->succeeded()->create();

        (new SendBackupHeartbeat)->handle(new BackupSucceeded($backup));

        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === self::HEARTBEAT_URL);
    }

    public function test_sends_nothing_without_a_heartbeat_url(): void
    {
        config(['backups.heartbeat_url' => '']);
        Http::preventStrayRequests();
        Http::fake([self::HEARTBEAT_URL => Http::response('OK')]);
        $backup = Backup::factory()->succeeded()->create();

        (new SendBackupHeartbeat)->handle(new BackupSucceeded($backup));

        Http::assertNothingSent();
    }

    public function test_throws_when_the_ping_fails_so_it_is_retried(): void
    {
        config(['backups.heartbeat_url' => self::HEARTBEAT_URL]);
        Http::preventStrayRequests();
        Http::fake([self::HEARTBEAT_URL => Http::response('', 500)]);
        $backup = Backup::factory()->succeeded()->create();

        $this->expectException(RequestException::class);

        (new SendBackupHeartbeat)->handle(new BackupSucceeded($backup));
    }
}
