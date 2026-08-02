<?php

declare(strict_types=1);

namespace App\Enums;

enum ChargeStatus: string
{
    /** Proposed to the client, not yet agreed. Does not count toward totals. */
    case Quoted = 'quoted';

    /** Agreed. This is the only status that adds to what the client owes. */
    case Approved = 'approved';

    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Quoted => 'Quoted',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Quoted => 'due',
            self::Approved => 'paid',
            self::Rejected, self::Cancelled => 'overdue',
        };
    }

    /**
     * Only approved charges move money.
     *
     * A quote sitting in the system must never inflate what a client appears to
     * owe, or what a partner appears to have earned.
     */
    public function countsTowardTotals(): bool
    {
        return $this === self::Approved;
    }
}
