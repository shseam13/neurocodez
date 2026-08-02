<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Renders an amount as words for the "In words:" line on an invoice.
 *
 * Uses the South Asian scale — thousand, lakh, crore — rather than the
 * international million/billion, because that is how a figure is read and
 * cross-checked in Bangladesh. "One lakh twenty thousand" is what a client
 * expects to see next to 1,20,000, and a mismatch there is exactly the sort of
 * thing that stalls a payment.
 */
final class AmountInWords
{
    private const ONES = [
        0 => '', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five',
        6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten',
        11 => 'eleven', 12 => 'twelve', 13 => 'thirteen', 14 => 'fourteen',
        15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen', 18 => 'eighteen',
        19 => 'nineteen',
    ];

    private const TENS = [
        2 => 'twenty', 3 => 'thirty', 4 => 'forty', 5 => 'fifty',
        6 => 'sixty', 7 => 'seventy', 8 => 'eighty', 9 => 'ninety',
    ];

    /** Minor-unit names per currency, for the fractional part. */
    private const SUBUNITS = [
        'BDT' => 'poisha',
        'INR' => 'paise',
        'USD' => 'cents',
        'EUR' => 'cents',
        'GBP' => 'pence',
    ];

    private const MAJOR_NAMES = [
        'BDT' => 'taka',
        'INR' => 'rupees',
        'USD' => 'dollars',
        'EUR' => 'euros',
        'GBP' => 'pounds',
    ];

    /** e.g. "Thirty thousand taka only" */
    public static function of(Money $money): string
    {
        $minor = abs($money->minor);
        $major = intdiv($minor, 100);
        $fraction = $minor % 100;

        $currency = strtoupper($money->currency);
        $majorName = self::MAJOR_NAMES[$currency] ?? $currency;
        $subunitName = self::SUBUNITS[$currency] ?? 'cents';

        $words = $major === 0 ? 'zero' : self::convert($major);
        $sentence = "{$words} {$majorName}";

        if ($fraction > 0) {
            $sentence .= ' and '.self::convert($fraction)." {$subunitName}";
        }

        if ($money->isNegative()) {
            $sentence = "minus {$sentence}";
        }

        return ucfirst(trim($sentence)).' only';
    }

    /**
     * Split on the South Asian grouping: crore, lakh, thousand, then hundreds.
     */
    private static function convert(int $number): string
    {
        if ($number < 0) {
            return 'minus '.self::convert(-$number);
        }

        $parts = [];

        foreach ([10_000_000 => 'crore', 100_000 => 'lakh', 1_000 => 'thousand'] as $unit => $name) {
            if ($number >= $unit) {
                $count = intdiv($number, $unit);
                $parts[] = self::convert($count)." {$name}";
                $number %= $unit;
            }
        }

        if ($number >= 100) {
            $parts[] = self::ONES[intdiv($number, 100)].' hundred';
            $number %= 100;
        }

        if ($number > 0) {
            $parts[] = $number < 20
                ? self::ONES[$number]
                : trim(self::TENS[intdiv($number, 10)].' '.self::ONES[$number % 10]);
        }

        return trim(implode(' ', array_filter($parts)));
    }
}
