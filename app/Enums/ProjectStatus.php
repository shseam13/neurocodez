<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case OnHold = 'on_hold';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnHold => 'On hold',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Maps to the semantic colour tokens in the design system. */
    public function tone(): string
    {
        return match ($this) {
            self::Active => 'info',
            self::OnHold => 'due',
            self::Delivered => 'paid',
            self::Cancelled => 'overdue',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Active, self::OnHold], true);
    }
}
