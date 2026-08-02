<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Queued, and it has to be.
 *
 * Sent inline, the whole SMTP handshake happens inside the HTTP request. A slow
 * or unreachable mail host then holds the connection open until nginx gives up
 * at fastcgi_read_timeout and returns 504 — the invitation may or may not have
 * gone out, and the person who clicked has no idea which.
 *
 * The `Queueable` trait alone does nothing; ShouldQueue is what moves the send
 * onto the supervised queue worker.
 */
class AccountInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $acceptUrl,
        private readonly ?string $invitedBy = null,
        private readonly int $expiresInDays = 7,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $company = CompanySetting::current()->name;
        $audience = match (true) {
            $notifiable->isClient() => 'track your projects, files and invoices',
            $notifiable->isPartner() => 'see the projects you brought us and what you have earned',
            default => 'manage clients, projects and invoices',
        };

        return (new MailMessage)
            ->subject("You have been invited to {$company}")
            ->greeting("Hello {$notifiable->name},")
            ->line($this->invitedBy
                ? "{$this->invitedBy} has invited you to {$company}."
                : "You have been invited to {$company}.")
            ->line("Set a password and you can {$audience}.")
            ->action('Set your password', $this->acceptUrl)
            ->line("This link expires in {$this->expiresInDays} days.")
            // The link is the only credential in this email; no password is ever
            // sent, so a forwarded or leaked message cannot hand over an account
            // after it has been used or expired.
            ->line('If you were not expecting this, you can ignore this email — nothing will happen.')
            ->salutation("— {$company}");
    }
}
