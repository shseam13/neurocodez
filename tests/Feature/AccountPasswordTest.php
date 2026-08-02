<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(AccountType $type = AccountType::Staff): User
    {
        $this->seed(PermissionSeeder::class);

        $attributes = [
            'name' => 'Test '.$type->value,
            'email' => $type->value.'@example.com',
            'password' => Hash::make('old-password'),
            'type' => $type,
            'is_active' => true,
            'email_verified_at' => now(),
        ];

        if ($type === AccountType::Client) {
            $attributes['client_id'] = Client::create(['name' => 'Acme Ltd'])->id;
        }

        if ($type === AccountType::Partner) {
            $attributes['partner_id'] = Partner::create([
                'name' => 'Karim', 'default_commission_percent' => 10,
            ])->id;
        }

        $user = User::query()->forceCreate($attributes);

        if ($type === AccountType::Staff) {
            $user->assignRole(User::ROLE_SUPER_ADMIN);
        }

        return $user;
    }

    #[Test]
    public function a_user_can_change_their_own_password(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'old-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertRedirect(route('account.edit'))
            ->assertSessionHas('status');

        $user->refresh();

        $this->assertTrue(Hash::check('a-brand-new-password', $user->password));
        $this->assertFalse(Hash::check('old-password', $user->password));
    }

    #[Test]
    public function the_current_password_must_be_correct(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'not-the-right-one',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertSessionHasErrors('current_password');

        // The whole point of the check: an unattended signed-in browser must
        // not be enough to take the account over.
        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }

    #[Test]
    public function the_new_password_must_be_confirmed(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'old-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'something-else-entirely',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('old-password', $user->refresh()->password));
    }

    #[Test]
    public function guests_cannot_reach_the_account_page(): void
    {
        $this->get(route('account.edit'))->assertRedirect(route('login'));
        $this->put(route('account.password'), [])->assertRedirect(route('login'));
    }

    /**
     * Clients and partners are not lesser admins, but they own their own
     * credentials — the page lives outside the account-type groups so all
     * three can reach it.
     */
    /**
     * The point of changing a password is to lock out whoever else had it.
     * If their session row survives, they stay signed in and the change
     * achieved nothing.
     */
    #[Test]
    public function changing_the_password_drops_other_sessions_but_keeps_this_one(): void
    {
        config(['session.driver' => 'database']);

        $user = $this->makeUser();

        $other = User::query()->forceCreate([
            'name' => 'Someone Else',
            'email' => 'someone-else@example.com',
            'password' => Hash::make('their-password'),
            'type' => AccountType::Staff,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $rows = [
            ['id' => 'intruder-session', 'user_id' => $user->id],
            ['id' => 'someone-elses', 'user_id' => $other->id],
        ];

        foreach ($rows as $row) {
            DB::table('sessions')->insert($row + [
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => base64_encode('x'),
                'last_activity' => time(),
            ]);
        }

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'old-password',
                'password' => 'a-brand-new-password',
                'password_confirmation' => 'a-brand-new-password',
            ])
            ->assertRedirect(route('account.edit'));

        $this->assertDatabaseMissing('sessions', ['id' => 'intruder-session']);

        // Another account's session must be untouched.
        $this->assertDatabaseHas('sessions', ['id' => 'someone-elses']);
    }

    #[Test]
    public function a_client_can_change_their_password(): void
    {
        $this->assertPortalUserCanChangePassword(AccountType::Client);
    }

    #[Test]
    public function a_partner_can_change_their_password(): void
    {
        $this->assertPortalUserCanChangePassword(AccountType::Partner);
    }

    /**
     * One session per user on purpose.
     *
     * Looping two account types through a single test session fails, because
     * AuthenticateSession compares the session's stored password hash against
     * the current user's — swapping identities mid-session is treated as a
     * hijacked session and signs you out. That is the middleware working, not
     * a bug, but it makes a loop here misleading.
     */
    private function assertPortalUserCanChangePassword(AccountType $type): void
    {
        $user = $this->makeUser($type);

        $this->actingAs($user)->get(route('account.edit'))->assertOk();

        $this->actingAs($user)
            ->put(route('account.password'), [
                'current_password' => 'old-password',
                'password' => 'portal-new-password',
                'password_confirmation' => 'portal-new-password',
            ])
            ->assertRedirect(route('account.edit'));

        $this->assertTrue(
            Hash::check('portal-new-password', $user->refresh()->password),
            "{$type->value} could not change their password"
        );
    }
}
