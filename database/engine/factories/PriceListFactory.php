<?php

namespace Database\Engine\Factories;

use App\Engine\Domain\Pricing\PriceTable;
use App\Engine\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Listino mensile con prezzi lineari (base + prezzo per giorno), sempre completo 1..30.
 *
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    public function definition(): array
    {
        return [
            'name' => 'Listino di prova',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-30',
            'extra_day_cents' => 1000,
            'source' => PriceList::SOURCE_MANUAL,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (PriceList $priceList): void {
            if ($priceList->prices()->doesntExist()) {
                $priceList->syncPrices(self::linearCents());
            }
        });
    }

    public function period(string $startsOn, string $endsOn): static
    {
        return $this->state(['starts_on' => $startsOn, 'ends_on' => $endsOn]);
    }

    /**
     * @param  array<int, int>  $centsByDays
     */
    public function withPrices(array $centsByDays): static
    {
        return $this->afterCreating(fn (PriceList $priceList) => $priceList->syncPrices($centsByDays));
    }

    /**
     * @return array<int, int> N giorni = base + N × prezzo al giorno
     */
    public static function linearCents(int $baseCents = 1000, int $perDayCents = 500): array
    {
        $cents = [];
        foreach (range(1, PriceTable::DAYS) as $days) {
            $cents[$days] = $baseCents + $perDayCents * $days;
        }

        return $cents;
    }
}
