<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's reset-password notification, moved onto the queue.
 *
 * The framework default sends inline, so a slow mail host stalls the request
 * and nginx returns 504 at fastcgi_read_timeout — exactly the failure that hit
 * invitations. Password reset is worse to get wrong: it is the screen someone
 * uses when they are already locked out.
 *
 * Subclassing rather than reimplementing keeps the token handling, the signed
 * URL and the expiry wording as the framework maintains them.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
