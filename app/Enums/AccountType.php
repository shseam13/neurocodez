<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which application an account belongs to.
 *
 * This is NOT a permission level. Client and Partner are not low-privilege
 * staff — they are separate audiences with their own routes, layouts and data
 * scopes. Roles (super_admin / admin) apply only to Staff.
 */
enum AccountType: string
{
    case Staff = 'staff';
    case Client = 'client';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Client => 'Client',
            self::Partner => 'Partner',
        };
    }

    /** The route the user lands on after signing in. */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Staff => 'dashboard',
            self::Client => 'portal.client.dashboard',
            self::Partner => 'portal.partner.dashboard',
        };
    }
}
