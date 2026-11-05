<?php

namespace Database\Engine\Seeders;

use App\Engine\Domain\Pricing\Discount\DiscountType;
use App\Engine\Models\Discount;
use App\Engine\Models\PriceList;
use Illuminate\Database\Seeder;

/**
 * Dati di esempio per lo sviluppo locale: un listino per l'anno in corso e il
 * successivo (prezzi del legacy di novembre 2026) e un codice sconto al 10%.
 *
 * php artisan db:seed --class="Database\Engine\Seeders\EngineSeeder"
 */
class EngineSeeder extends Seeder
{
    /** Novembre 2026 nel legacy: 1 giorno 17 €, 30 giorni 165 €. */
    public const SAMPLE_CENTS = [
        1 => 1700, 2200, 2700, 3200, 3700, 4200, 4700, 5200, 5700, 6200,
        6700, 7200, 7800, 8550, 9000, 9500, 10000, 10500, 11000, 11500,
        12000, 12500, 13000, 13500, 14000, 14500, 15000, 15500, 16000, 16500,
    ];

    public function run(): void
    {
        $year = (int) now()->format('Y');

        $priceList = PriceList::query()->firstOrCreate(
            ['name' => 'Listino di esempio', 'source' => PriceList::SOURCE_MANUAL],
            ['starts_on' => "{$year}-01-01", 'ends_on' => ($year + 1).'-12-31', 'extra_day_cents' => 1000],
        );
        $priceList->syncPrices(self::SAMPLE_CENTS);

        Discount::query()->firstOrCreate(
            ['code' => 'BENVENUTO10'],
            ['type' => DiscountType::Percentage, 'percentage' => 10],
        );
    }
}
