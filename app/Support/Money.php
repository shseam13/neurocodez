<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An immutable amount held in MINOR UNITS (poisha for BDT, cents elsewhere).
 *
 * Nothing in this application may hold money in a float. 0.1 + 0.2 !== 0.3 in
 * binary floating point, and across a few hundred partial payments and
 * percentage-based commissions that error compounds into figures that do not
 * reconcile — on invoices a client is looking at.
 *
 * Integers cannot drift. Rounding happens once, explicitly, and only where a
 * fractional result is unavoidable (percentages).
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(
        public int $minor,
        public string $currency = 'BDT',
    ) {}

    public static function ofMinor(int $minor, string $currency = 'BDT'): self
    {
        return new self($minor, strtoupper($currency));
    }

    /** Build from a human-entered value such as "1,250.50". */
    public static function ofMajor(int|float|string $major, string $currency = 'BDT'): self
    {
        if (is_string($major)) {
            $major = str_replace([',', ' ', "\u{09F3}", "\u{20B9}"], '', trim($major));
            if ($major === '' || ! is_numeric($major)) {
                throw new InvalidArgumentException("Not a valid amount: {$major}");
            }
        }

        // round() before casting: (int) truncates, so (int)(1.15 * 100) can
        // yield 114 because 1.15 is not exactly representable.
        return new self((int) round((float) $major * 100), strtoupper($currency));
    }

    public static function zero(string $currency = 'BDT'): self
    {
        return new self(0, strtoupper($currency));
    }

    public function plus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor + $other->minor, $this->currency);
    }

    public function minus(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->minor - $other->minor, $this->currency);
    }

    /**
     * A percentage of this amount, e.g. a partner commission.
     *
     * The multiplication stays exact: minor units are well under 2^53 for any
     * realistic amount, so the float product is exact and only the final
     * division needs rounding (half away from zero).
     */
    public function percent(float|string $percent): self
    {
        return new self(
            (int) round($this->minor * (float) $percent / 100, 0, PHP_ROUND_HALF_UP),
            $this->currency,
        );
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    /** Clamp at zero — a balance owed should never display as negative. */
    public function floorAtZero(): self
    {
        return $this->minor < 0 ? new self(0, $this->currency) : $this;
    }

    /** Magnitude, e.g. to phrase an overpayment as a positive figure. */
    public function abs(): self
    {
        return $this->minor < 0 ? new self(-$this->minor, $this->currency) : $this;
    }

    public function negate(): self
    {
        return new self(-$this->minor, $this->currency);
    }

    public function toMajor(): float
    {
        return $this->minor / 100;
    }

    /** e.g. "50,000.00" */
    public function format(bool $decimals = true): string
    {
        return number_format($this->minor / 100, $decimals ? 2 : 0, '.', ',');
    }

    /**
     * e.g. "BDT 50,000.00".
     *
     * The ISO code is used rather than the ৳ glyph because the same helper
     * feeds the dompdf invoice templates, and dompdf's bundled DejaVu fonts
     * carry no Bengali script — U+09F3 renders there as an empty box unless a
     * Bengali-capable font has been registered.
     */
    public function formatWithCurrency(bool $decimals = true): string
    {
        return $this->currency.' '.$this->format($decimals);
    }

    public function jsonSerialize(): array
    {
        return [
            'minor' => $this->minor,
            'currency' => $this->currency,
            'formatted' => $this->formatWithCurrency(),
        ];
    }

    public function __toString(): string
    {
        return $this->formatWithCurrency();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                "Cannot combine {$this->currency} with {$other->currency}."
            );
        }
    }
}
