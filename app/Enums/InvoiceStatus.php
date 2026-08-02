<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'info',
            self::Sent => 'due',
            self::Paid => 'paid',
            self::Void => 'overdue',
        };
    }

    /** A sent or paid invoice has left the building; editing it is not allowed. */
    public function isLocked(): bool
    {
        return $this !== self::Draft;
    }
}
