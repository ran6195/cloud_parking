<?php

namespace Tests\Unit;

use App\Services\PriceCalculator;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private function calculator(): PriceCalculator
    {
        return new PriceCalculator(upTo3Days: 25, week: 45, twoWeeks: 75, extraDay: 5, coveredPerDay: 3);
    }

    public function test_a_stay_shorter_than_24_hours_counts_as_one_day(): void
    {
        $from = Carbon::parse('2026-10-01 06:00');

        $this->assertSame(1, $this->calculator()->days($from, $from->copy()->addHours(5)));
        $this->assertSame(1, $this->calculator()->days($from, $from->copy()->addHours(24)));
    }

    public function test_every_started_24_hour_period_counts_as_a_full_day(): void
    {
        $from = Carbon::parse('2026-10-01 06:00');

        $this->assertSame(2, $this->calculator()->days($from, $from->copy()->addHours(24)->addMinute()));
        $this->assertSame(3, $this->calculator()->days($from, Carbon::parse('2026-10-03 22:30')));
    }

    public function test_return_must_be_after_departure(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $from = Carbon::parse('2026-10-01 06:00');
        $this->calculator()->days($from, $from);
    }

    /**
     * @return array<string, array{int, float}>
     */
    public static function thresholds(): array
    {
        return [
            '1 giorno' => [1, 25.0],
            '3 giorni (soglia)' => [3, 25.0],
            '4 giorni' => [4, 45.0],
            '7 giorni (soglia)' => [7, 45.0],
            '8 giorni' => [8, 75.0],
            '14 giorni (soglia)' => [14, 75.0],
            '15 giorni' => [15, 80.0],
            '20 giorni' => [20, 105.0],
        ];
    }

    #[DataProvider('thresholds')]
    public function test_price_follows_the_package_thresholds(int $days, float $expected): void
    {
        $this->assertSame($expected, $this->calculator()->priceForDays($days));
    }

    public function test_covered_spot_adds_a_daily_supplement(): void
    {
        $this->assertSame(45.0 + 5 * 3.0, $this->calculator()->priceForDays(5, covered: true));
    }

    public function test_quote_combines_days_and_price(): void
    {
        $quote = $this->calculator()->quote(Carbon::parse('2026-10-01 06:00'), Carbon::parse('2026-10-08 23:00'));

        $this->assertSame(['days' => 8, 'price' => 75.0], $quote);
    }
}
