<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\CompanySetting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountInvitation extends Notification
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
