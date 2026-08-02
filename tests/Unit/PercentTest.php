<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Percent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PercentTest extends TestCase
{
    #[Test]
    public function it_does_not_eat_a_significant_trailing_zero(): void
    {
        /*
         * The regression this class exists for.
         *
         * rtrim(rtrim((string) 10, '0'), '.') returns "1" — a 10% commission
         * rendered as 1%. It only looks correct when the value happens to carry
         * decimals ("10.00"), which is exactly why it survived review.
         */
        $this->assertSame('10', Percent::format(10));
        $this->assertSame('10', Percent::format(10.0));
        $this->assertSame('10', Percent::format('10'));
        $this->assertSame('10', Percent::format('10.00'));
        $this->assertSame('10%', Percent::withSign(10));

        $this->assertSame('100', Percent::format(100));
        $this->assertSame('20', Percent::format('20.000'));
    }

    #[Test]
    public function it_keeps_meaningful_decimals(): void
    {
        $this->assertSame('12.5', Percent::format(12.5));
        $this->assertSame('12.5', Percent::format('12.50'));
        $this->assertSame('7.25', Percent::format(7.25));
        $this->assertSame('0.5', Percent::format(0.5));
    }

    #[Test]
    public function it_handles_zero_and_empty_values(): void
    {
        $this->assertSame('0', Percent::format(0));
        $this->assertSame('0', Percent::format('0.00'));
        $this->assertSame('0', Percent::format(null));
        $this->assertSame('0', Percent::format(''));
    }
}
