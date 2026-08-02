<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CompanySetting;
use App\Models\User;

/**
 * The wording of an invitation, in one place.
 *
 * Both the emailed notification and the copy-and-paste version in the admin are
 * built from this. Written twice they would drift, and the pasted one is the
 * only one anybody actually receives right now — the host blocks outbound SMTP,
 * so invitations are sent by hand.
 */
final class InvitationMessage
{
    public function __construct(
        private readonly User $user,
        private readonly string $acceptUrl,
        private readonly ?string $invitedBy = null,
        private readonly int $expiresInDays = 7,
    ) {}

    /**
     * CompanySetting::current() creates the row on demand but does not populate
     * it, so `name` is null until someone fills in Settings. Interpolating that
     * silently produced "You have been invited to " — a real invitation with
     * the company name missing. Fall back to the configured app name.
     */
    public function company(): string
    {
        return CompanySetting::current()->name ?: (string) config('app.name');
    }

    public function subject(): string
    {
        return "You have been invited to {$this->company()}";
    }

    /** What this person will actually be able to do once they are in. */
    public function audience(): string
    {
        return match (true) {
            $this->user->isClient() => 'track your projects, files and invoices',
            $this->user->isPartner() => 'see the projects you brought us and what you have earned',
            default => 'manage clients, projects and invoices',
        };
    }

    public function greeting(): string
    {
        return "Hello {$this->user->name},";
    }

    public function invitedByLine(): string
    {
        return $this->invitedBy
            ? "{$this->invitedBy} has invited you to {$this->company()}."
            : "You have been invited to {$this->company()}.";
    }

    public function purposeLine(): string
    {
        return "Set a password and you can {$this->audience()}.";
    }

    public function expiryLine(): string
    {
        return "This link expires in {$this->expiresInDays} days.";
    }

    /**
     * The link is the only credential in here. No password is ever sent, so a
     * forwarded message cannot hand over an account after it has been used.
     */
    public function reassuranceLine(): string
    {
        return 'If you were not expecting this, you can ignore this message — nothing will happen.';
    }

    public function signOff(): string
    {
        return "— {$this->company()}";
    }

    /**
     * The whole thing as plain text, ready to paste into WhatsApp or an email.
     *
     * Plain text rather than HTML on purpose: it survives being pasted into a
     * chat app, and a messaging client will linkify the URL by itself.
     */
    public function toPlainText(): string
    {
        return implode("\n", [
            $this->greeting(),
            '',
            $this->invitedByLine(),
            $this->purposeLine(),
            '',
            'Set your password here:',
            $this->acceptUrl,
            '',
            $this->expiryLine(),
            $this->reassuranceLine(),
            '',
            $this->signOff(),
        ]);
    }
}
