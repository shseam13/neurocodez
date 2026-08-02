<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Support\InvitationMessage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates accounts by invitation.
 *
 * Nobody's password is ever typed by someone else. The invitee receives a
 * signed, expiring link and chooses their own — which means a leaked invite
 * email cannot hand over an account once it has been used.
 */
class InvitationService
{
    private const EXPIRES_DAYS = 7;

    public function inviteStaff(string $name, string $email, string $role, ?User $by = null): User
    {
        $user = $this->createOrReviveUser($name, $email, AccountType::Staff);

        // syncRoles rather than assignRole: re-inviting someone should not
        // silently leave an old role attached alongside the new one.
        $user->syncRoles([$role]);

        return $this->send($user, $by);
    }

    public function inviteClient(Client $client, string $name, string $email, ?User $by = null): User
    {
        $user = $this->createOrReviveUser($name, $email, AccountType::Client);
        $user->forceFill(['client_id' => $client->id, 'partner_id' => null])->save();

        return $this->send($user, $by);
    }

    public function invitePartner(Partner $partner, string $name, string $email, ?User $by = null): User
    {
        $user = $this->createOrReviveUser($name, $email, AccountType::Partner);
        $user->forceFill(['partner_id' => $partner->id, 'client_id' => null])->save();

        return $this->send($user, $by);
    }

    /** Re-send without changing anything else about the account. */
    public function resend(User $user, ?User $by = null): User
    {
        if (! $user->hasPendingInvitation()) {
            throw new RuntimeException("{$user->name} has already set a password.");
        }

        return $this->send($user, $by);
    }

    public function revoke(User $user): void
    {
        if (! $user->hasPendingInvitation()) {
            throw new RuntimeException('That invitation has already been accepted.');
        }

        // Never accepted, so there is nothing worth keeping.
        $user->forceDelete();
    }

    /**
     * A signed URL rather than a stored token.
     *
     * Laravel signs and expires it, so there is no extra table to keep clean —
     * and the id alone is useless without the signature.
     */
    public function acceptUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'invitation.accept',
            now()->addDays(self::EXPIRES_DAYS),
            ['user' => $user->getKey()],
        );
    }

    /**
     * The invitation as text, for sending by hand.
     *
     * The host blocks outbound SMTP, so this — not the email — is how people
     * actually receive their invitation. Built from the same InvitationMessage
     * the notification uses, with a freshly signed link.
     *
     * $by is optional because the admin renders this outside the request that
     * created the invitation, where the original inviter is no longer known.
     */
    public function messageFor(User $user, ?User $by = null): InvitationMessage
    {
        return new InvitationMessage(
            $user,
            $this->acceptUrl($user),
            $by?->name,
            self::EXPIRES_DAYS,
        );
    }

    private function send(User $user, ?User $by): User
    {
        $user->forceFill(['invited_at' => now()])->save();

        $user->notify(new AccountInvitation(
            $this->acceptUrl($user),
            $by?->name,
            self::EXPIRES_DAYS,
        ));

        return $user;
    }

    /**
     * Reuse an existing row where possible.
     *
     * Emails are unique, so re-inviting an address that already exists must
     * update that account rather than fail — including one that was previously
     * soft-deleted.
     */
    private function createOrReviveUser(string $name, string $email, AccountType $type): User
    {
        $user = User::withTrashed()->firstWhere('email', $email);

        if ($user) {
            if ($user->trashed()) {
                $user->restore();
            }

            /*
             * An account that has already set a password keeps it. Changing the
             * type of a live account is refused outright — quietly converting a
             * staff member into a client would strip their access in a way
             * nobody would think to look for.
             */
            if (! $user->hasPendingInvitation() && $user->type !== $type) {
                throw new RuntimeException(
                    "{$email} already has an active {$user->type->label()} account. ".
                    'Deactivate it before inviting them as something else.'
                );
            }

            $user->forceFill(['name' => $name, 'type' => $type, 'is_active' => true])->save();

            return $user;
        }

        return User::create([
            'name' => $name,
            'email' => $email,
            // Unusable until they set their own. Not a guessable placeholder.
            'password' => Str::random(64),
            'type' => $type,
            'is_active' => true,
            'invited_at' => now(),
        ]);
    }
}
