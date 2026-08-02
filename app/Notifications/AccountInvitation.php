<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Support\InvitationMessage;
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
        // Same wording as the copy-and-paste version in the admin. Written
        // twice, the two would drift apart.
        $message = $this->message($notifiable);

        return (new MailMessage)
            ->subject($message->subject())
            ->greeting($message->greeting())
            ->line($message->invitedByLine())
            ->line($message->purposeLine())
            ->action('Set your password', $this->acceptUrl)
            ->line($message->expiryLine())
            ->line($message->reassuranceLine())
            ->salutation($message->signOff());
    }

    public function message(User $notifiable): InvitationMessage
    {
        return new InvitationMessage(
            $notifiable,
            $this->acceptUrl,
            $this->invitedBy,
            $this->expiresInDays,
        );
    }
}
