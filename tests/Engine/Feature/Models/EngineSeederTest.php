<?php

use App\Engine\Domain\Pricing\Discount\DiscountCode;
use App\Engine\Infrastructure\Pricing\DatabaseDiscountFinder;
use App\Engine\Infrastructure\Pricing\DatabaseTariffSource;
use App\Engine\Models\PriceList;
use Database\Engine\Seeders\EngineSeeder;
use Tests\Concerns\RefreshEngineDatabase;

uses(RefreshEngineDatabase::class);

it('prepara un listino di esempio e un codice sconto, anche se rieseguito', function () {
    $this->seed(EngineSeeder::class);
    $this->seed(EngineSeeder::class);

    $match = (new DatabaseTariffSource)->lookup(new DateTimeImmutable('now'));

    expect(PriceList::query()->count())->toBe(1)
        ->and($match?->table->totalFor(3)->cents)->toBe(2700)
        ->and($match?->table->totalFor(30)->cents)->toBe(16500)
        ->and((new DatabaseDiscountFinder)->find(new DiscountCode('benvenuto10'))?->percentage)->toBe(10);
});
