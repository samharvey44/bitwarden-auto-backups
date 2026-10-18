<?php

namespace App\Notifications;

use App\Models\Backup;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VaultBackupFailed extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Backup $backup)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $vault = $this->backup->vault;

        return (new MailMessage)
            ->error()
            ->subject("Backup of {$vault->name} failed")
            ->greeting('A backup failed')
            ->line("The latest backup of {$vault->name} failed with this error:")
            ->line($this->backup->error ?? 'No reason was recorded.')
            ->action('View vault', route('vaults.show', $vault))
            ->line("You won't be notified about this vault again until one of its backups succeeds.");
    }
}
