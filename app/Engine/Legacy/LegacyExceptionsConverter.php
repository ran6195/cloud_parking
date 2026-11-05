<?php

namespace App\Engine\Legacy;

use App\Engine\Domain\Pricing\PriceTable;
use DateTimeImmutable;

/**
 * Converte la tabella legacy "exceptions" in listini mensili completi.
 *
 * Nel legacy la riga del giorno N di un mese contiene il prezzo totale di una
 * sosta di N giorni iniziata in quel mese. Regole:
 * - i mesi senza alcun prezzo (costo NULL) vengono scartati: a runtime vale il fallback sull'anno precedente;
 * - i mesi con buchi tra i giorni presenti vengono scartati e segnalati;
 * - nei mesi corti i giorni mancanti fino al 30° proseguono l'ultimo incremento
 *   (es. febbraio: 27 gg = 150 €, 28 gg = 155 € → 29 gg = 160 €, 30 gg = 165 €);
 * - la riga del 31° giorno è ignorata, come nel legacy (oltre il 30° vale il prezzo del giorno aggiuntivo);
 * - un prezzo inferiore a quello del giorno precedente viene importato ma segnalato.
 */
final class LegacyExceptionsConverter
{
    private const MONTHS = [
        1 => 'Gennaio', 'Febbraio', 'Marzo', 'Aprile', 'Maggio', 'Giugno',
        'Luglio', 'Agosto', 'Settembre', 'Ottobre', 'Novembre', 'Dicembre',
    ];

    /**
     * @param  iterable<array{date: string, cost: float|int|string|null}>  $rows
     */
    public function convert(iterable $rows): LegacyExceptionsConversion
    {
        /** @var array<string, array<int, float|int|string|null>> $months */
        $months = [];
        foreach ($rows as $row) {
            $date = new DateTimeImmutable($row['date']);
            $months[$date->format('Y-m')][(int) $date->format('j')] = $row['cost'];
        }
        ksort($months);

        $priceLists = [];
        $skipped = [];
        $warnings = [];

        foreach ($months as $yearMonth => $costs) {
            $first = new DateTimeImmutable($yearMonth.'-01');
            $daysInMonth = (int) $first->format('t');
            $available = min($daysInMonth, PriceTable::DAYS);

            $present = array_filter($costs, fn ($cost) => $cost !== null && $cost !== '');
            if ($present === []) {
                $skipped[] = "{$yearMonth}: nessun prezzo";

                continue;
            }

            $missing = array_values(array_filter(range(1, $available), fn (int $day) => ! isset($present[$day])));
            if ($missing !== []) {
                $skipped[] = "{$yearMonth}: mancano i prezzi dei giorni ".implode(', ', $missing);

                continue;
            }

            $cents = [];
            $previous = 0;
            $lastStep = 0;
            foreach (range(1, $available) as $day) {
                $value = (int) round(((float) $present[$day]) * 100);

                if ($value < 0) {
                    $skipped[] = "{$yearMonth}: prezzo negativo per {$day} giorni";

                    continue 2;
                }
                if ($day > 1 && $value < $previous) {
                    $warnings[] = "{$yearMonth}: {$day} giorni costano meno di ".($day - 1);
                }

                $lastStep = $day === 1 ? 0 : max(0, $value - $previous);
                $cents[$day] = $previous = $value;
            }

            $filled = [];
            for ($day = $available + 1; $day <= PriceTable::DAYS; $day++) {
                $cents[$day] = $previous += $lastStep;
                $filled[] = $day;
            }

            $priceLists[] = new LegacyMonthlyPriceList(
                yearMonth: $yearMonth,
                startsOn: $first->format('Y-m-d'),
                endsOn: $first->format('Y-m-t'),
                name: self::MONTHS[(int) $first->format('n')].' '.$first->format('Y'),
                centsByDays: $cents,
                filledDays: $filled,
            );
        }

        return new LegacyExceptionsConversion($priceLists, $skipped, $warnings);
    }
}
