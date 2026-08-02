<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    #[Test]
    public function it_stores_major_amounts_as_exact_minor_units(): void
    {
        $this->assertSame(5_000_000, Money::ofMajor(50000)->minor);
        $this->assertSame(125_050, Money::ofMajor('1,250.50')->minor);
        $this->assertSame(10, Money::ofMajor(0.1)->minor);
        $this->assertSame(1_200_000, Money::ofMajor('৳12,000')->minor);
    }

    #[Test]
    public function it_does_not_lose_a_poisha_on_values_float_maths_gets_wrong(): void
    {
        // (int) (1.15 * 100) evaluates to 114 in binary floating point.
        $this->assertSame(115, Money::ofMajor(1.15)->minor);
        $this->assertSame(829, Money::ofMajor(8.29)->minor);
        $this->assertSame(29, Money::ofMajor(0.29)->minor);
    }

    #[Test]
    public function it_adds_without_drift_across_many_partial_payments(): void
    {
        // The scenario that breaks float-based systems: hundreds of small,
        // awkwardly sized payments against one balance.
        $balance = Money::ofMajor(50000);
        $paid = Money::zero();

        for ($i = 0; $i < 1000; $i++) {
            $paid = $paid->plus(Money::ofMajor(0.07));
        }

        $this->assertSame(7_000, $paid->minor);
        $this->assertSame(4_993_000, $balance->minus($paid)->minor);
        $this->assertSame('49,930.00', $balance->minus($paid)->format());
    }

    #[Test]
    public function it_calculates_the_worked_commission_example_from_the_plan(): void
    {
        // BDT 50,000 project, 10% partner on a collected basis, 20,000 received.
        $agreed = Money::ofMajor(50000);
        $received = Money::ofMajor(20000);

        $due = $agreed->minus($received);
        $owed = $received->percent(10);

        $this->assertSame('30,000.00', $due->format());
        $this->assertSame('2,000.00', $owed->format());
        $this->assertTrue($owed->minus(Money::ofMajor(2000))->isZero());
    }

    #[Test]
    public function it_rounds_percentages_half_up_rather_than_truncating(): void
    {
        // 12.5 poisha must become 13, not 12.
        $this->assertSame(13, Money::ofMinor(125)->percent(10)->minor);
        $this->assertSame(5_000, Money::ofMajor(333.33)->percent(15)->minor);
    }

    #[Test]
    public function it_can_clamp_a_negative_balance_to_zero(): void
    {
        $overpaid = Money::ofMajor(100)->minus(Money::ofMajor(150));

        $this->assertTrue($overpaid->isNegative());
        $this->assertTrue($overpaid->floorAtZero()->isZero());
    }

    #[Test]
    public function it_refuses_to_mix_currencies(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::ofMajor(10, 'BDT')->plus(Money::ofMajor(10, 'USD'));
    }

    #[Test]
    public function it_formats_with_the_iso_code_not_the_taka_glyph(): void
    {
        // dompdf's bundled fonts carry no Bengali script, so ৳ would render as
        // an empty box on an invoice. The ISO code is safe everywhere.
        $this->assertSame('BDT 50,000.00', Money::ofMajor(50000)->formatWithCurrency());
        $this->assertSame('50,000', Money::ofMajor(50000)->format(decimals: false));
    }

    #[Test]
    public function it_rejects_input_that_is_not_a_number(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::ofMajor('not money');
    }
}
