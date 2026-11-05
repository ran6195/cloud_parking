<?php

namespace App\Engine\Console;

use App\Engine\Application\RefundService;
use App\Engine\Models\Reservation;
use Illuminate\Console\Command;

/**
 * Rimborsi delle prenotazioni annullate dopo il pagamento online.
 *
 *   engine:refunds                       elenca i rimborsi da fare
 *   engine:refunds 22171 --automatic     rimborsa su PayPal
 *   engine:refunds 22171 --mark-refunded segna come rimborsata a mano
 */
class RefundsCommand extends Command
{
    protected $signature = 'engine:refunds
        {number? : Numero della prenotazione}
        {--automatic : Rimborsa su PayPal}
        {--mark-refunded : Segna come già rimborsata (rimborso fatto a mano)}
        {--note= : Nota del gestore, con --mark-refunded}';

    protected $description = 'Elenca o esegue i rimborsi delle prenotazioni annullate';

    public function handle(RefundService $refunds): int
    {
        $number = $this->argument('number');

        if ($number === null) {
            return $this->list();
        }

        $reservation = Reservation::query()->where('number', (int) $number)->first();
        if ($reservation === null) {
            $this->components->error("Prenotazione n. {$number} inesistente.");

            return self::FAILURE;
        }

        if (! in_array(RefundService::status($reservation), [RefundService::REQUESTED, RefundService::FAILED], true)) {
            $this->components->error("La prenotazione n. {$number} non ha un rimborso da fare.");

            return self::FAILURE;
        }

        if ($this->option('automatic') === $this->option('mark-refunded')) {
            $this->components->error('Indica --automatic oppure --mark-refunded.');

            return self::FAILURE;
        }

        if ($this->option('automatic')) {
            $refunds->refundAutomatically($reservation);
        } else {
            $note = $this->option('note');
            $refunds->markRefunded($reservation, is_string($note) ? $note : null);
        }

        $status = RefundService::status($reservation->refresh());
        if ($status !== RefundService::REFUNDED) {
            $this->components->error('Rimborso non riuscito: '.($reservation->refund['error'] ?? 'errore sconosciuto'));

            return self::FAILURE;
        }

        $this->components->info("Prenotazione n. {$number} rimborsata.");

        return self::SUCCESS;
    }

    private function list(): int
    {
        $pending = Reservation::query()
            ->whereIn('refund->status', [RefundService::REQUESTED, RefundService::FAILED])
            ->orderBy('cancelled_at')
            ->get();

        if ($pending->isEmpty()) {
            $this->components->info('Nessun rimborso da fare.');

            return self::SUCCESS;
        }

        $this->table(['Numero', 'Annullata il', 'Importo €', 'Codice fiscale', 'Stato', 'Errore'], $pending->map(fn (Reservation $r) => [
            $r->number,
            $r->cancelled_at?->setTimezone('Europe/Rome')->format('d/m/Y H:i'),
            number_format(((int) ($r->refund['amount_cents'] ?? 0)) / 100, 2, ',', '.'),
            $r->refund['tax_code'] ?? '',
            $r->refund['status'] ?? '',
            $r->refund['error'] ?? '',
        ])->all());

        return self::SUCCESS;
    }
}
