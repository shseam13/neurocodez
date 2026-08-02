<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMethod: string
{
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Rocket = 'rocket';
    case Bank = 'bank';
    case Cash = 'cash';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bkash => 'bKash',
            self::Nagad => 'Nagad',
            self::Rocket => 'Rocket',
            self::Bank => 'Bank transfer',
            self::Cash => 'Cash',
            self::Other => 'Other',
        };
    }

    /** Methods that carry a transaction id worth recording. */
    public function expectsReference(): bool
    {
        return $this !== self::Cash;
    }
}
