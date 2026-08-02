<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use App\Notifications\AccountInvitation;
use App\Services\InvitationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Notification::fake();
    }

    private ?User $owner = null;

    private function owner(): User
    {
        return $this->owner ??= User::create([
            'name' => 'Owner', 'email' => 'owner@example.com', 'password' => Hash::make('x'),
            'type' => AccountType::Staff, 'is_active' => true,
        ])->assignRole(User::ROLE_SUPER_ADMIN);
    }

    private function service(): InvitationService
    {
        return app(InvitationService::class);
    }

    // -------------------------------------------------------------- inviting

    #[Test]
    public function inviting_staff_creates_a_pending_account_and_sends_a_link(): void
    {
        $this->actingAs($this->owner())->post('/admin/users', [
            'name' => 'Nadia', 'email' => 'nadia@example.com', 'role' => User::ROLE_ADMIN,
        ])->assertRedirect();

        $user = User::firstWhere('email', 'nadia@example.com');

        $this->assertNotNull($user);
        $this->assertTrue($user->hasPendingInvitation());
        $this->assertTrue($user->hasRole(User::ROLE_ADMIN));
        Notification::assertSentTo($user, AccountInvitation::class);
    }

    #[Test]
    public function no_password_is_ever_chosen_on_someone_elses_behalf(): void
    {
        $user = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);

        // The stored value is random and unusable; only the invitee sets a real one.
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertFalse(Hash::check('', $user->password));
        $this->assertTrue($user->hasPendingInvitation());
    }

    #[Test]
    public function a_client_invitation_is_linked_to_that_client(): void
    {
        $client = Client::create(['name' => 'Rahim Traders']);

        $this->actingAs($this->owner())
            ->post("/admin/clients/{$client->id}/invite", ['name' => 'Rahim', 'email' => 'rahim@example.com'])
            ->assertRedirect();

        $user = User::firstWhere('email', 'rahim@example.com');

        $this->assertSame(AccountType::Client, $user->type);
        $this->assertSame($client->id, $user->client_id);
        $this->assertNull($user->partner_id);
    }

    #[Test]
    public function a_partner_invitation_is_linked_to_that_partner(): void
    {
        $partner = Partner::create(['name' => 'Karim', 'default_commission_percent' => 10]);

        $this->actingAs($this->owner())
            ->post("/admin/partners/{$partner->id}/invite", ['name' => 'Karim', 'email' => 'karim@example.com'])
            ->assertRedirect();

        $user = User::firstWhere('email', 'karim@example.com');

        $this->assertSame(AccountType::Partner, $user->type);
        $this->assertSame($partner->id, $user->partner_id);
        $this->assertNull($user->client_id);
    }

    // -------------------------------------------------------------- accepting

    #[Test]
    public function accepting_sets_the_password_signs_them_in_and_lands_them_home(): void
    {
        $client = Client::create(['name' => 'Rahim Traders']);
        $user = $this->service()->inviteClient($client, 'Rahim', 'rahim@example.com');
        $url = $this->service()->acceptUrl($user);

        $this->get($url)->assertOk()->assertSee('Set password');

        $this->post($url, [
            'password' => 'a-real-password-99',
            'password_confirmation' => 'a-real-password-99',
        ])->assertRedirect('/portal');

        $user->refresh();

        $this->assertTrue(Hash::check('a-real-password-99', $user->password));
        $this->assertFalse($user->hasPendingInvitation());
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function an_invitation_link_is_single_use(): void
    {
        /*
         * The signed URL stays valid until it expires, so single use comes from
         * clearing invited_at. Otherwise a forwarded invite email would let
         * anyone reset the password of an account already in use.
         */
        $user = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);
        $url = $this->service()->acceptUrl($user);

        $this->post($url, ['password' => 'first-password-11', 'password_confirmation' => 'first-password-11'])
            ->assertRedirect('/dashboard');

        $this->post('/logout');

        $this->post($url, ['password' => 'hijacked-password', 'password_confirmation' => 'hijacked-password'])
            ->assertRedirect('/login');

        $this->assertTrue(Hash::check('first-password-11', $user->fresh()->password));
    }

    #[Test]
    public function an_unsigned_or_tampered_link_is_rejected(): void
    {
        $user = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);

        // No signature at all.
        $this->get("/invitation/{$user->id}")->assertForbidden();

        // Signature present but for a different user.
        $other = $this->service()->inviteStaff('Other', 'other@example.com', User::ROLE_ADMIN);
        $tampered = str_replace("/invitation/{$other->id}", "/invitation/{$user->id}", $this->service()->acceptUrl($other));

        $this->get($tampered)->assertForbidden();
    }

    #[Test]
    public function an_expired_link_is_rejected(): void
    {
        $user = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);
        $url = $this->service()->acceptUrl($user);

        $this->travel(8)->days();

        $this->get($url)->assertForbidden();
    }

    // ------------------------------------------------------------- lifecycle

    #[Test]
    public function an_invitation_can_be_revoked_before_it_is_accepted(): void
    {
        $user = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);

        $this->actingAs($this->owner())
            ->delete("/admin/users/{$user->id}/revoke")
            ->assertRedirect();

        // Never accepted, so nothing worth keeping.
        $this->assertDatabaseMissing('users', ['email' => 'nadia@example.com']);
    }

    #[Test]
    public function an_accepted_account_cannot_be_revoked(): void
    {
        $user = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);
        $user->forceFill(['invited_at' => null, 'password' => Hash::make('set')])->save();

        $this->actingAs($this->owner())
            ->delete("/admin/users/{$user->id}/revoke")
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['email' => 'nadia@example.com']);
    }

    #[Test]
    public function re_inviting_an_active_account_of_a_different_type_is_refused(): void
    {
        // Silently converting a staff member into a client would strip their
        // access in a way nobody would think to look for.
        $staff = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);
        $staff->forceFill(['invited_at' => null])->save();

        $client = Client::create(['name' => 'Rahim Traders']);

        $this->actingAs($this->owner())
            ->post("/admin/clients/{$client->id}/invite", ['name' => 'Nadia', 'email' => 'nadia@example.com'])
            ->assertSessionHasErrors('invite');

        $this->assertSame(AccountType::Staff, $staff->fresh()->type);
    }

    // ------------------------------------------------------------- lockout

    #[Test]
    public function the_last_owner_is_identified_correctly(): void
    {
        /*
         * Tested at the model rather than through a request, because the
         * deactivation path is unreachable by design: ManageUsers is owner-only,
         * and an owner cannot deactivate themselves. The guard in the controller
         * is defence in depth for that reason — this asserts the predicate it
         * depends on.
         */
        $owner = $this->owner();

        $this->assertTrue($owner->isLastSuperAdmin());

        $second = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_SUPER_ADMIN);

        $this->assertFalse($owner->fresh()->isLastSuperAdmin());

        // A deactivated owner does not count as cover for the other one.
        $second->forceFill(['is_active' => false])->save();

        $this->assertTrue($owner->fresh()->isLastSuperAdmin());
    }

    #[Test]
    public function a_plain_admin_cannot_deactivate_an_owner(): void
    {
        $owner = $this->owner();
        $admin = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);
        $admin->forceFill(['invited_at' => null])->save();

        $this->actingAs($admin)->put("/admin/users/{$owner->id}/active")->assertForbidden();

        $this->assertTrue($owner->fresh()->is_active);
    }

    #[Test]
    public function the_only_owner_cannot_be_demoted(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put("/admin/users/{$owner->id}/role", ['role' => User::ROLE_ADMIN])
            ->assertSessionHasErrors('user');

        $this->assertTrue($owner->fresh()->hasRole(User::ROLE_SUPER_ADMIN));
    }

    #[Test]
    public function an_owner_can_be_demoted_once_a_second_owner_exists(): void
    {
        $owner = $this->owner();
        $second = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_SUPER_ADMIN);

        $this->actingAs($second)
            ->put("/admin/users/{$owner->id}/role", ['role' => User::ROLE_ADMIN])
            ->assertRedirect();

        $this->assertFalse($owner->fresh()->hasRole(User::ROLE_SUPER_ADMIN));
    }

    #[Test]
    public function you_cannot_deactivate_your_own_account(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put("/admin/users/{$owner->id}/active")
            ->assertSessionHasErrors('user');
    }

    #[Test]
    public function a_deactivated_user_cannot_sign_in(): void
    {
        $user = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);
        $user->forceFill([
            'invited_at' => null, 'password' => Hash::make('known-password'), 'is_active' => false,
        ])->save();

        $this->post('/login', ['email' => 'nadia@example.com', 'password' => 'known-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    // ------------------------------------------------------------ permissions

    #[Test]
    public function a_plain_admin_cannot_manage_people(): void
    {
        // ManageUsers is owner-only by design.
        $admin = $this->service()->inviteStaff('Nadia', 'nadia@example.com', User::ROLE_ADMIN);
        $admin->forceFill(['invited_at' => null])->save();

        $this->actingAs($admin)->get('/admin/users')->assertForbidden();
        $this->actingAs($admin)->post('/admin/users', [
            'name' => 'X', 'email' => 'x@example.com', 'role' => User::ROLE_ADMIN,
        ])->assertForbidden();
    }

    #[Test]
    public function portal_accounts_cannot_reach_people_management(): void
    {
        $client = Client::create(['name' => 'Rahim Traders']);
        $user = $this->service()->inviteClient($client, 'Rahim', 'rahim@example.com');
        $user->forceFill(['invited_at' => null])->save();

        $this->actingAs($user)->get('/admin/users')->assertRedirect('/portal');
    }
}
