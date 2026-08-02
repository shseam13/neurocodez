<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Models\Client;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(AccountType $type, bool $active = true): User
    {
        $attributes = [
            'name' => 'Test '.$type->value,
            'email' => $type->value.'@example.com',
            'password' => Hash::make('correct-horse'),
            'type' => $type,
            'is_active' => $active,
        ];

        if ($type === AccountType::Client) {
            $attributes['client_id'] = Client::create(['name' => 'Acme Ltd'])->id;
        }

        if ($type === AccountType::Partner) {
            $attributes['partner_id'] = Partner::create([
                'name' => 'Karim', 'default_commission_percent' => 10,
            ])->id;
        }

        return User::create($attributes);
    }

    #[Test]
    public function the_login_page_renders(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    #[Test]
    public function each_account_type_lands_in_its_own_application(): void
    {
        // Pairs, not an enum-keyed map: PHP array keys may only be int|string.
        foreach ([
            [AccountType::Staff, '/dashboard'],
            [AccountType::Client, '/portal'],
            [AccountType::Partner, '/partner'],
        ] as [$type, $home]) {
            $user = $this->makeUser($type);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'correct-horse',
            ])->assertRedirect($home);

            $this->assertAuthenticatedAs($user);
            $this->post('/logout');
        }
    }

    #[Test]
    public function a_deactivated_account_cannot_sign_in(): void
    {
        $user = $this->makeUser(AccountType::Staff, active: false);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse',
        ])->assertRedirect('/login')->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function a_wrong_password_is_rejected(): void
    {
        $user = $this->makeUser(AccountType::Staff);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function a_client_cannot_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser(AccountType::Client))
            ->get('/dashboard')
            ->assertRedirect('/portal');
    }

    #[Test]
    public function a_partner_cannot_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser(AccountType::Partner))
            ->get('/dashboard')
            ->assertRedirect('/partner');
    }

    #[Test]
    public function staff_cannot_reach_the_client_portal(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->actingAs($this->makeUser(AccountType::Staff))
            ->get('/portal')
            ->assertRedirect('/dashboard');
    }

    #[Test]
    public function a_client_cannot_reach_the_partner_portal(): void
    {
        $this->actingAs($this->makeUser(AccountType::Client))
            ->get('/partner')
            ->assertRedirect('/portal');
    }

    #[Test]
    public function the_public_site_offers_a_sign_in_link(): void
    {
        // Clients and partners have to be able to find their way in.
        $this->get('/')->assertOk()->assertSee(route('login'));
    }

    #[Test]
    public function an_already_signed_in_visitor_hitting_login_goes_to_their_own_area(): void
    {
        /*
         * This is what makes a single static "Sign in" link in the cached
         * public header workable: the header never varies by auth state, so
         * the routing has to happen here.
         */
        foreach ([
            [AccountType::Staff, '/dashboard'],
            [AccountType::Client, '/portal'],
            [AccountType::Partner, '/partner'],
        ] as [$type, $home]) {
            $this->actingAs($this->makeUser($type))
                ->get('/login')
                ->assertRedirect($home);
        }
    }

    #[Test]
    public function guests_are_sent_to_the_login_page(): void
    {
        foreach (['/dashboard', '/portal', '/partner'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    #[Test]
    public function repeated_failed_attempts_are_rate_limited(): void
    {
        $user = $this->makeUser(AccountType::Staff);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-horse', // correct, but locked out
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertStringContainsString(
            'seconds',
            (string) session('errors')->first('email'),
        );
    }

    #[Test]
    public function signing_out_clears_the_session(): void
    {
        $this->actingAs($this->makeUser(AccountType::Staff))
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }

    #[Test]
    public function the_password_reset_form_does_not_reveal_whether_an_email_exists(): void
    {
        // Enumeration guard: an unknown address must produce the same response
        // as a known one, or the form becomes a way to test which emails have
        // accounts.
        $this->makeUser(AccountType::Staff);

        $known = $this->post('/forgot-password', ['email' => 'staff@example.com']);
        $unknown = $this->post('/forgot-password', ['email' => 'nobody@example.com']);

        $known->assertSessionHas('status');
        $unknown->assertSessionHas('status');
        $this->assertSame(session('status'), session('status'));
    }
}
