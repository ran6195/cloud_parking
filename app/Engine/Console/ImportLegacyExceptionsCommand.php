<?php

namespace App\Engine\Console;

use App\Engine\EngineServiceProvider;
use App\Engine\Legacy\LegacyExceptionsConverter;
use App\Engine\Legacy\LegacyMonthlyPriceList;
use App\Engine\Models\PriceList;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa i prezzi legacy (tabella "exceptions") come listini mensili del motore.
 *
 * Idempotente: un mese già importato viene aggiornato solo se i prezzi sono
 * cambiati. Un mese che si sovrappone a un listino creato a mano non viene toccato.
 */
class ImportLegacyExceptionsCommand extends Command
{
    protected $signature = 'engine:import-legacy-exceptions
        {--connection=legacy : Connessione al DB legacy}
        {--table=exceptions : Tabella legacy con i prezzi mensili}
        {--extra-day-cents=1000 : Prezzo di ogni giorno oltre il 30°, in centesimi (nel legacy 10 € fissi)}
        {--dry-run : Mostra cosa farebbe senza scrivere nulla}';

    protected $description = 'Importa i prezzi della tabella legacy "exceptions" come listini mensili del motore';

    public function handle(LegacyExceptionsConverter $converter): int
    {
        $extraDayCents = filter_var($this->option('extra-day-cents'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($extraDayCents === false) {
            $this->components->error('--extra-day-cents deve essere un intero maggiore o uguale a 0.');

            return self::FAILURE;
        }

        $rows = DB::connection((string) $this->option('connection'))
            ->table((string) $this->option('table'))
            ->whereNotNull('data')
            ->orderBy('data')
            ->get(['data', 'costo'])
            ->map(fn (object $row) => ['date' => (string) $row->data, 'cost' => $row->costo]);

        $conversion = $converter->convert($rows);
        $dryRun = (bool) $this->option('dry-run');
        $outcome = ['creato' => 0, 'aggiornato' => 0, 'invariato' => 0, 'in conflitto' => 0];
        $conflicts = [];
        $report = [];

        $import = function () use ($conversion, $extraDayCents, $dryRun, &$outcome, &$conflicts, &$report): void {
            foreach ($conversion->priceLists as $month) {
                $result = $this->importMonth($month, $extraDayCents, $dryRun);
                $outcome[$result]++;

                if ($result === 'in conflitto') {
                    $conflicts[] = "{$month->yearMonth}: si sovrappone a un listino creato a mano";
                }

                $report[] = [
                    $month->yearMonth,
                    $result,
                    number_format($month->centsByDays[1] / 100, 2, ',', '.'),
                    number_format($month->centsByDays[30] / 100, 2, ',', '.'),
                    $month->filledDays === [] ? '' : implode(', ', $month->filledDays),
                ];
            }
        };

        $dryRun ? $import() : DB::connection(EngineServiceProvider::CONNECTION)->transaction($import);

        $this->table(['Mese', 'Esito', '1 giorno €', '30 giorni €', 'Giorni riempiti'], $report);

        $isEmptyMonth = fn (string $line) => str_ends_with($line, ': nessun prezzo');
        $empty = array_filter($conversion->skipped, $isEmptyMonth);
        $skipped = array_filter($conversion->skipped, fn (string $line) => ! $isEmptyMonth($line));

        if ($empty !== []) {
            $this->components->warn('Mesi senza prezzi (vale il fallback sugli anni precedenti): '
                .implode(', ', array_map(fn (string $line) => substr($line, 0, 7), $empty)));
        }
        foreach ([...$skipped, ...$conflicts] as $line) {
            $this->components->warn("Scartato {$line}");
        }
        foreach ($conversion->warnings as $line) {
            $this->components->warn("Anomalia {$line}");
        }

        $summary = collect($outcome)->map(fn (int $count, string $label) => "{$count} {$label}")->implode(', ');
        $this->components->info(($dryRun ? '[dry-run] Nessuna modifica salvata. ' : '')
            .'Listini: '.$summary.'; mesi scartati: '.count($conversion->skipped).'.');

        return self::SUCCESS;
    }

    private function importMonth(LegacyMonthlyPriceList $month, int $extraDayCents, bool $dryRun): string
    {
        $overlapping = PriceList::query()
            ->where('starts_on', '<=', $month->endsOn)
            ->where('ends_on', '>=', $month->startsOn)
            ->with('prices')
            ->get();

        $existing = $overlapping->first(fn (PriceList $list) => $list->source === PriceList::SOURCE_LEGACY
            && $list->starts_on->format('Y-m-d') === $month->startsOn
            && $list->ends_on->format('Y-m-d') === $month->endsOn);

        if ($overlapping->count() > ($existing === null ? 0 : 1)) {
            return 'in conflitto';
        }

        if ($existing !== null) {
            if ($existing->centsByDays() === $month->centsByDays
                && $existing->extra_day_cents === $extraDayCents
                && $existing->name === $month->name) {
                return 'invariato';
            }

            if (! $dryRun) {
                $existing->update(['name' => $month->name, 'extra_day_cents' => $extraDayCents]);
                $existing->syncPrices($month->centsByDays);
            }

            return 'aggiornato';
        }

        if (! $dryRun) {
            PriceList::create([
                'name' => $month->name,
                'starts_on' => $month->startsOn,
                'ends_on' => $month->endsOn,
                'extra_day_cents' => $extraDayCents,
                'source' => PriceList::SOURCE_LEGACY,
            ])->syncPrices($month->centsByDays);
        }

        return 'creato';
    }
}
