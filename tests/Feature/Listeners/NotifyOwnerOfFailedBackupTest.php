<?php

namespace Tests\Feature\Listeners;

use App\Enums\BackupStatus;
use App\Events\BackupFailed;
use App\Listeners\NotifyOwnerOfFailedBackup;
use App\Models\Backup;
use App\Models\User;
use App\Models\Vault;
use App\Notifications\VaultBackupFailed;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class NotifyOwnerOfFailedBackupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_listens_for_failed_backups(): void
    {
        Event::fake();

        Event::assertListening(BackupFailed::class, NotifyOwnerOfFailedBackup::class);
    }

    /**
     * @param  list<BackupStatus>  $earlierStatuses
     */
    #[DataProvider('firstFailuresDataProvider')]
    public function test_notifies_the_owner_about_the_first_failure_in_a_row(array $earlierStatuses): void
    {
        Notification::fake();
        $owner = User::factory()->create();
        $vault = Vault::factory()->for($owner)->create();
        foreach ($earlierStatuses as $status) {
            Backup::factory()->for($vault)->create(['status' => $status]);
        }
        $failed = Backup::factory()->for($vault)->failed()->create();

        $this->handle($failed);

        Notification::assertSentTo($owner, VaultBackupFailed::class, fn (VaultBackupFailed $notification): bool => $notification->backup->is($failed));
        Notification::assertCount(1);
    }

    public function test_does_not_notify_again_while_the_vault_keeps_failing(): void
    {
        Notification::fake();
        $vault = Vault::factory()->create();
        Backup::factory()->for($vault)->succeeded()->create();
        Backup::factory()->for($vault)->failed()->create();
        $failedAgain = Backup::factory()->for($vault)->failed()->create();

        $this->handle($failedAgain);

        Notification::assertNothingSent();
    }

    public function test_the_mail_message_names_the_vault_gives_the_error_and_links_to_it(): void
    {
        $vault = Vault::factory()->create(['name' => 'Personal']);
        $failed = Backup::factory()->for($vault)->failed()->create();

        $mail = (new VaultBackupFailed($failed))->toMail($vault->user);

        $this->assertSame('Backup of Personal failed', $mail->subject);
        $this->assertSame('error', $mail->level);
        $this->assertContains('Bitwarden CLI command [unlock] failed with exit code 1: Invalid master password.', $mail->introLines);
        $this->assertSame("http://localhost/vaults/{$vault->id}", $mail->actionUrl);
    }

    /**
     * @return array<string, array{list<BackupStatus>}>
     */
    public static function firstFailuresDataProvider(): array
    {
        return [
            'the vault\'s first backup' => [[]],
            'after a successful backup' => [[BackupStatus::Failed, BackupStatus::Succeeded]],
            'ignoring backups that never finished' => [[BackupStatus::Succeeded, BackupStatus::Pending]],
        ];
    }

    /**
     * Run the listener for the failed backup.
     */
    private function handle(Backup $backup): void
    {
        (new NotifyOwnerOfFailedBackup)->handle(new BackupFailed($backup));
    }
}
