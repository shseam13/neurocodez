<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Formats commission rates for display.
 *
 * Exists because the obvious one-liner is wrong:
 *
 *     rtrim(rtrim((string) $value, '0'), '.')
 *
 * That trims trailing zeros to turn "10.00" into "10" — but applied to a value
 * with no decimal point it eats a significant digit, rendering 10 as "1". On a
 * commission rate that is not a cosmetic bug: it misstates what is owed.
 */
final class Percent
{
    /** 10 => "10", 12.50 => "12.5", 7.25 => "7.25" */
    public static function format(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        $number = (float) $value;

        // Only strip zeros when a decimal point is actually present.
        $formatted = number_format($number, 2, '.', '');

        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }

    /** Same, with the sign appended: "12.5%" */
    public static function withSign(float|int|string|null $value): string
    {
        return self::format($value).'%';
    }
}
