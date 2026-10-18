<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's password reset email, sent from the queue.
 *
 * Sending it during the request would make requests for real accounts
 * measurably slower than for unknown email addresses, revealing who has an
 * account. It is encrypted while queued, as it carries the reset token.
 */
class QueuedResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
}
