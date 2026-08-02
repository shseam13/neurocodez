<?php

declare(strict_types=1);

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a bigint column of minor units to a {@see Money} object.
 *
 * The currency comes from the model's own `currency` column when it has one, so
 * an amount always knows what it is denominated in rather than assuming BDT.
 *
 * @implements CastsAttributes<Money, Money|int|float|string|null>
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        return Money::ofMinor((int) $value, $this->currency($attributes));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->minor;
        }

        // Integers arriving here are ambiguous, so treat every scalar as a
        // human-entered major amount. Code that means minor units should pass a
        // Money object — being explicit is the point of the type.
        return Money::ofMajor($value, $this->currency($attributes))->minor;
    }

    private function currency(array $attributes): string
    {
        return $attributes['currency'] ?? 'BDT';
    }
}
