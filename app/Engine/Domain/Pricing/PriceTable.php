<?php

namespace App\Engine\Domain\Pricing;

use App\Engine\Domain\Shared\Money;
use InvalidArgumentException;

/**
 * Listino: prezzo forfettario totale per 1..30 giorni, più un prezzo per ogni
 * giorno oltre il 30°. Sempre completo: niente righe mancanti né buchi.
 */
final readonly class PriceTable
{
    public const DAYS = 30;

    /** @var array<int, Money> */
    private array $totals;

    /**
     * @param  array<int, Money>  $totals  prezzo totale indicizzato per numero di giorni (1..30)
     * @param  string  $label  nome leggibile, ad es. "Novembre 2026"
     */
    public function __construct(
        array $totals,
        public Money $extraDayPrice,
        public string $label,
    ) {
        $expected = range(1, self::DAYS);
        $days = array_keys($totals);
        sort($days);

        if ($days !== $expected) {
            $missing = implode(', ', array_diff($expected, $days));
            throw new InvalidArgumentException("Listino \"{$label}\" incompleto: mancano i giorni [{$missing}].");
        }

        foreach ($totals as $days => $price) {
            if ($price->isNegative()) {
                throw new InvalidArgumentException("Listino \"{$label}\": prezzo negativo per {$days} giorni.");
            }
        }

        if ($extraDayPrice->isNegative()) {
            throw new InvalidArgumentException("Listino \"{$label}\": prezzo del giorno aggiuntivo negativo.");
        }

        ksort($totals);
        $this->totals = $totals;
    }

    /**
     * @param  array<int, int>  $centsByDays
     */
    public static function fromCents(array $centsByDays, int $extraDayCents, string $label): self
    {
        return new self(
            array_map(Money::cents(...), $centsByDays),
            Money::cents($extraDayCents),
            $label,
        );
    }

    public function totalFor(int $days): Money
    {
        if ($days < 1 || $days > self::DAYS) {
            throw new InvalidArgumentException("Numero di giorni fuori listino: {$days}.");
        }

        return $this->totals[$days];
    }

    public function priceFor(StayDuration $duration): Money
    {
        return $this->totalFor($duration->includedDays)
            ->plus($this->extraDayPrice->times($duration->extraDays));
    }

    /**
     * @return array<int, Money>
     */
    public function totals(): array
    {
        return $this->totals;
    }
}
