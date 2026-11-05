<?php

namespace App\Engine\Console;

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Models\Reservation;
use Illuminate\Console\Command;

/**
 * Porta a "expired" le prenotazioni online non pagate entro il termine.
 * Il posto è già libero dal termine in poi (vedi docs/availability.md): qui si sistema lo stato.
 * Pianificato ogni minuto dall'EngineServiceProvider.
 */
class ExpirePendingReservationsCommand extends Command
{
    protected $signature = 'engine:expire-pending';

    protected $description = 'Fa scadere le prenotazioni online non pagate entro il termine';

    public function handle(Clock $clock): int
    {
        $expired = 0;

        Reservation::query()
            ->where('status', ReservationStatus::PendingPayment)
            ->where('payment_due_by', '<=', $clock->now()->format(DATE_ATOM))
            ->each(function (Reservation $reservation) use (&$expired): void {
                $reservation->transitionTo(ReservationStatus::Expired);
                $expired++;
            });

        $this->components->info("Prenotazioni scadute: {$expired}.");

        return self::SUCCESS;
    }
}
