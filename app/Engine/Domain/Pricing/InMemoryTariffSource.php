<?php

namespace App\Engine\Domain\Pricing;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Listini in memoria per periodo (estremi inclusi). Usato da test e simulazioni.
 */
final class InMemoryTariffSource implements TariffSource
{
    /** @var list<array{from: string, to: string, table: PriceTable}> */
    private array $periods = [];

    public function add(string $from, string $to, PriceTable $table): self
    {
        if ($to < $from) {
            throw new InvalidArgumentException("Periodo non valido: {$from} → {$to}.");
        }

        foreach ($this->periods as $period) {
            if ($from <= $period['to'] && $to >= $period['from']) {
                throw new InvalidArgumentException("Il periodo {$from} → {$to} si sovrappone a {$period['from']} → {$period['to']}.");
            }
        }

        $this->periods[] = ['from' => $from, 'to' => $to, 'table' => $table];

        return $this;
    }

    /**
     * Comodità per i listini mensili: "2026-11" copre dal 1° all'ultimo giorno del mese.
     */
    public function addMonth(string $yearMonth, PriceTable $table): self
    {
        $first = new DateTimeImmutable($yearMonth.'-01');

        return $this->add($first->format('Y-m-d'), $first->format('Y-m-t'), $table);
    }

    public function lookup(DateTimeImmutable $date): ?TariffMatch
    {
        $day = $date->format('Y-m-d');

        foreach ($this->periods as $period) {
            if ($period['from'] <= $day && $day <= $period['to']) {
                return new TariffMatch($period['table'], $date, $date);
            }
        }

        return null;
    }
}
