<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * When a partner's commission is considered earned.
 *
 * There is no universally correct answer, so this is per-project data rather
 * than a hardcoded rule.
 */
enum CommissionBasis: string
{
    /**
     * Accrues only on money actually received. The safe default: you never owe
     * a payout on an invoice the client never paid.
     */
    case Collected = 'collected';

    /** Accrues on the full agreed value the moment the deal is signed. */
    case Agreed = 'agreed';

    public function label(): string
    {
        return match ($this) {
            self::Collected => 'On money collected',
            self::Agreed => 'On agreed value',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Collected => 'Commission builds up only as the client actually pays.',
            self::Agreed => 'Full commission is owed as soon as the project is agreed.',
        };
    }
}
