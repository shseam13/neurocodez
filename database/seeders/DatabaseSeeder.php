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

        $owner = User::query()->withTrashed()->firstWhere('email', $email);

        if ($owner) {
            $owner->restore();
        } else {
            $password = env('SEED_OWNER_PASSWORD', 'ChangeMe!'.bin2hex(random_bytes(4)));

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
