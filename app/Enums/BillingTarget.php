<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who receives the invoice for a project.
 *
 * The two arrangements are genuinely different money flows, not a labelling
 * preference, and one partner may work both ways depending on the deal.
 */
enum BillingTarget: string
{
    /** You invoice the end client; the partner earns commission on what you collect. */
    case Client = 'client';

    /**
     * The partner owns the client relationship and pays you an agreed net.
     *
     * `agreed_amount` is then YOUR share. There is no commission to calculate —
     * the partner's cut never passes through your books at all, so counting it
     * again would be double-charging yourself.
     */
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Client => 'The client pays me',
            self::Partner => 'The partner pays me a net amount',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Client => 'You invoice the client for the full amount and pay the partner their percentage.',
            self::Partner => 'The partner bills the client and pays you your share. No commission is tracked — the agreed amount is already your net.',
        };
    }

    public function earnsCommission(): bool
    {
        return $this === self::Client;
    }
}
