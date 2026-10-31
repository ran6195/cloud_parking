<?php

namespace Tests\Engine\Support;

use App\Engine\Domain\Pricing\Discount\Discount;
use App\Engine\Domain\Pricing\Discount\DiscountPolicy;
use App\Engine\Domain\Pricing\Discount\InMemoryDiscountFinder;
use App\Engine\Domain\Pricing\ExtraDefinition;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\ExtraPricing;
use App\Engine\Domain\Pricing\ExtrasCatalog;
use App\Engine\Domain\Pricing\PaymentFees;
use App\Engine\Domain\Pricing\PreviousYearsTariffSource;
use App\Engine\Domain\Pricing\PriceTable;
use App\Engine\Domain\Pricing\QuoteCalculator;
use App\Engine\Domain\Pricing\TariffSource;
use App\Engine\Domain\Shared\FrozenClock;
use App\Engine\Domain\Shared\Money;

/**
 * Dati di prova per i test del motore prezzi.
 */
final class Pricing
{
    /**
     * Listino lineare: N giorni costano 10 € + 5 € × N; ogni giorno oltre il 30° costa $extraDayCents.
     */
    public static function linearTable(string $label = 'Test', int $baseCents = 1000, int $perDayCents = 500, int $extraDayCents = 1000): PriceTable
    {
        $totals = [];
        foreach (range(1, PriceTable::DAYS) as $days) {
            $totals[$days] = $baseCents + $perDayCents * $days;
        }

        return PriceTable::fromCents($totals, $extraDayCents, $label);
    }

    /**
     * Catalogo con i valori del legacy al 31/10/2026.
     */
    public static function catalog(bool $washActive = true): ExtrasCatalog
    {
        return new ExtrasCatalog([
            new ExtraDefinition(ExtraId::CoveredParking, 'Posto coperto', ExtraPricing::PercentageOfParking,
                percentage: 40, capacityLimited: true, incompatibleWith: [ExtraId::KeyDrop]),
            new ExtraDefinition(ExtraId::ExteriorWash, 'Lavaggio esterno', ExtraPricing::Fixed,
                active: $washActive, price: Money::cents(1500)),
            new ExtraDefinition(ExtraId::InteriorWash, 'Pulizia abitacolo', ExtraPricing::Fixed,
                active: false, price: Money::cents(1500)),
            new ExtraDefinition(ExtraId::KeyDrop, 'Tieni le chiavi', ExtraPricing::Fixed,
                price: Money::cents(700), capacityLimited: true, incompatibleWith: [ExtraId::CoveredParking]),
            new ExtraDefinition(ExtraId::EvCharging, 'Ricarica auto elettrica', ExtraPricing::Informational,
                message: 'Il costo della ricarica dipenderà dal costo del kWh al momento della ricarica'),
            new ExtraDefinition(ExtraId::TyreRepair, 'Riparazione gomme', ExtraPricing::Informational,
                active: false, message: 'Il costo della riparazione è di 20€ a chiodo'),
        ]);
    }

    public static function calculator(
        TariffSource $tariffs,
        ?ExtrasCatalog $catalog = null,
        string $today = '2026-10-31 12:00:00 Europe/Rome',
        Discount ...$discounts,
    ): QuoteCalculator {
        return new QuoteCalculator(
            tariffs: new PreviousYearsTariffSource($tariffs),
            extras: $catalog ?? self::catalog(),
            fees: new PaymentFees(Money::cents(100)),
            discounts: new DiscountPolicy(new InMemoryDiscountFinder(...$discounts)),
            clock: FrozenClock::at($today),
        );
    }
}
