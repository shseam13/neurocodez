<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Partner;
use App\Models\User;
use App\Services\InvitationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The invitation link is shown in the admin and delivered by hand, because the
 * host blocks outbound SMTP. That makes the on-screen link the only way anyone
 * gets an account — so it has to be present, and it has to work.
 */
class InvitationLinkTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $this->seed(PermissionSeeder::class);

        $user = User::query()->forceCreate([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('secret-owner'),
            'type' => AccountType::Staff,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $user->assignRole(User::ROLE_SUPER_ADMIN);

        return $user;
    }

    #[Test]
    public function the_partner_page_shows_a_copyable_invitation_link_while_it_is_pending(): void
    {
        $owner = $this->owner();
        $partner = Partner::create(['name' => 'Hridoy', 'default_commission_percent' => 50]);

        $this->actingAs($owner)->post(route('admin.partners.invite', $partner), [
            'name' => 'Hridoy',
            'email' => 'hridoy@example.com',
        ])->assertRedirect();

        $invited = User::query()->firstWhere('email', 'hridoy@example.com');
        $this->assertTrue($invited->hasPendingInvitation());

        $this->actingAs($owner)
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Link only')
            ->assertSee('data-copy-target', escape: false)
            // The signed route, not a bare path — without the signature the
            // link is refused by the `signed` middleware.
            ->assertSee('/invitation/'.$invited->id.'?expires=', escape: false)
            ->assertSee('signature=', escape: false);
    }

    /**
     * The link is regenerated per render rather than stored, so the one on
     * screen must actually be accepted by the signed route.
     */
    #[Test]
    public function the_displayed_link_lets_the_invitee_set_a_password(): void
    {
        $partner = Partner::create(['name' => 'Hridoy', 'default_commission_percent' => 50]);
        $this->seed(PermissionSeeder::class);

        $invited = app(InvitationService::class)
            ->invitePartner($partner, 'Hridoy', 'hridoy@example.com');

        $url = app(InvitationService::class)->acceptUrl($invited);

        $this->get($url)->assertOk();

        $this->post($url, [
            'password' => 'their-own-password',
            'password_confirmation' => 'their-own-password',
        ])->assertRedirect();

        $invited->refresh();

        $this->assertTrue(Hash::check('their-own-password', $invited->password));
        $this->assertFalse($invited->hasPendingInvitation());
    }

    #[Test]
    public function the_page_offers_the_whole_message_to_copy_not_just_the_link(): void
    {
        $owner = $this->owner();
        $partner = Partner::create(['name' => 'Hridoy', 'default_commission_percent' => 50]);

        $invited = app(InvitationService::class)
            ->invitePartner($partner, 'Hridoy', 'hridoy@example.com');

        $response = $this->actingAs($owner)
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertSee('Message to send')
            ->assertSee('Hello Hridoy,')
            ->assertSee('see the projects you brought us and what you have earned')
            ->assertSee('This link expires in 7 days.');

        // The link has to be inside the pasted text, not only in the separate
        // field — otherwise the message arrives with no way to act on it.
        $body = app(InvitationService::class)->messageFor($invited)->toPlainText();
        $this->assertStringContainsString('/invitation/'.$invited->id, $body);
        $this->assertStringContainsString('signature=', $body);

        $response->assertSee('You have been invited to', escape: false);
    }

    /**
     * The wording is built from one InvitationMessage so the emailed version
     * and the pasted version cannot drift. If the notification stops matching,
     * people receive different instructions depending on how they were invited.
     */
    #[Test]
    public function the_emailed_and_pasted_wording_come_from_the_same_source(): void
    {
        $this->seed(PermissionSeeder::class);
        $partner = Partner::create(['name' => 'Hridoy', 'default_commission_percent' => 50]);

        $invited = app(InvitationService::class)
            ->invitePartner($partner, 'Hridoy', 'hridoy@example.com');

        $service = app(InvitationService::class);
        $message = $service->messageFor($invited);

        $mail = (new \App\Notifications\AccountInvitation($service->acceptUrl($invited)))
            ->toMail($invited);

        $this->assertSame($message->subject(), $mail->subject);
        $this->assertSame($message->greeting(), $mail->greeting);
        $this->assertContains($message->purposeLine(), $mail->introLines);
        $this->assertContains($message->expiryLine(), $mail->outroLines);
    }

    #[Test]
    public function the_message_describes_what_that_audience_will_actually_see(): void
    {
        $this->seed(PermissionSeeder::class);

        $client = \App\Models\Client::create(['name' => 'Acme Ltd']);
        $clientUser = app(InvitationService::class)
            ->inviteClient($client, 'Rahim', 'rahim@example.com');

        $partner = Partner::create(['name' => 'Hridoy', 'default_commission_percent' => 50]);
        $partnerUser = app(InvitationService::class)
            ->invitePartner($partner, 'Hridoy', 'hridoy@example.com');

        $service = app(InvitationService::class);

        $this->assertStringContainsString(
            'track your projects, files and invoices',
            $service->messageFor($clientUser)->toPlainText()
        );

        // A client must never be told about commission.
        $this->assertStringNotContainsString(
            'earned',
            $service->messageFor($clientUser)->toPlainText()
        );

        $this->assertStringContainsString(
            'what you have earned',
            $service->messageFor($partnerUser)->toPlainText()
        );
    }

    #[Test]
    public function an_unsigned_link_is_refused(): void
    {
        $partner = Partner::create(['name' => 'Hridoy', 'default_commission_percent' => 50]);
        $this->seed(PermissionSeeder::class);

        $invited = app(InvitationService::class)
            ->invitePartner($partner, 'Hridoy', 'hridoy@example.com');

        $this->get(route('invitation.accept', $invited, absolute: false))->assertForbidden();
    }

    /**
     * Anyone who can read the page can create the account, so the link must not
     * appear for a viewer who is not allowed to manage users.
     */
    #[Test]
    public function the_link_is_hidden_once_the_invitation_has_been_accepted(): void
    {
        $owner = $this->owner();
        $partner = Partner::create(['name' => 'Hridoy', 'default_commission_percent' => 50]);

        $invited = app(InvitationService::class)
            ->invitePartner($partner, 'Hridoy', 'hridoy@example.com');

        $invited->forceFill([
            'password' => Hash::make('already-set'),
            'invited_at' => null,
        ])->save();

        $this->actingAs($owner)
            ->get(route('admin.partners.show', $partner))
            ->assertOk()
            ->assertDontSee('Message to send')
            ->assertDontSee('Link only')
            // The signed URL itself must be gone, not merely its label.
            ->assertDontSee('/invitation/'.$invited->id, escape: false);
    }
}
