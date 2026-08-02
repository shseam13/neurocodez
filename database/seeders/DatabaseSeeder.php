<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            StageSetSeeder::class,
        ]);

        CompanySetting::query()->firstOrCreate([], [
            'name' => 'Neuro Codez',
            'slogan' => 'Connect . Create . Serve',
            'invoice_prefix' => 'INV',
            'invoice_next_number' => 1,
            'currency' => 'BDT',
        ]);

        $this->seedOwner();
    }

    /**
     * The first account is the owner, and the system must always keep at least
     * one — see User::isLastSuperAdmin(), which blocks deleting or demoting the
     * last one so you cannot lock yourself out of your own company.
     */
    private function seedOwner(): void
    {
        $email = env('SEED_OWNER_EMAIL', 'seam.cse.pciu@gmail.com');
        $configured = env('SEED_OWNER_PASSWORD');

        $owner = User::query()->withTrashed()->firstWhere('email', $email);

        if ($owner) {
            $owner->restore();

            /*
             * Deliberate escape hatch, and the only one that exists.
             *
             * On a host with no shell there is no `artisan` to run, so a lost
             * owner password would otherwise lock you out of your own company
             * permanently — and password reset is no help until mail and
             * APP_URL are both working, which is exactly when you are most
             * likely to be locked out.
             *
             * Setting SEED_OWNER_PASSWORD resets the password on the next
             * deploy. REMOVE THE VARIABLE once you are signed in: while it is
             * set, every deploy overwrites whatever password you chose in the
             * UI, silently undoing the change.
             */
            if (filled($configured)) {
                $owner->forceFill([
                    'password' => Hash::make($configured),
                    'is_active' => true,
                ])->save();

                $this->command?->warn("Owner password reset from SEED_OWNER_PASSWORD: {$email}");
                $this->command?->warn('Remove SEED_OWNER_PASSWORD now, or every deploy will reset it again.');
            }
        } else {
            $password = $configured ?: 'ChangeMe!'.bin2hex(random_bytes(4));

            $owner = User::create([
                'name' => env('SEED_OWNER_NAME', 'Neuro Codez Owner'),
                'email' => $email,
                'password' => Hash::make($password),
                'type' => AccountType::Staff,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->command?->warn("Owner account created: {$email}");
            $this->command?->warn("Temporary password: {$password}");
            $this->command?->warn('Change this immediately after first sign-in.');
        }

        if (! $owner->hasRole(User::ROLE_SUPER_ADMIN)) {
            $owner->assignRole(User::ROLE_SUPER_ADMIN);
        }
    }
}
