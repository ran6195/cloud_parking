<?php

namespace App\Engine\Application;

use App\Engine\Jobs\GenerateReceipt;
use App\Engine\Mail\ReservationCancelled;
use App\Engine\Mail\ReservationCancelledNotice;
use App\Engine\Mail\ReservationConfirmed;
use App\Engine\Mail\ReservationReceived;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email e ricevuta di una prenotazione confermata, in coda dopo il commit.
 * Un errore qui non annulla mai la prenotazione: email_sent resta false e l'errore viene registrato.
 */
final readonly class ReservationNotifier
{
    public function __construct(private EngineSettings $settings) {}

    public function confirmed(Reservation $reservation): bool
    {
        GenerateReceipt::dispatch($reservation->id);

        try {
            Mail::to($reservation->owner()->email, $reservation->owner()->first_name.' '.$reservation->owner()->last_name)
                ->queue(new ReservationConfirmed(
                    $reservation,
                    $this->settings->brandName(),
                    $this->settings->contactPhone(),
                    self::cancellationUrl($reservation),
                ));

            $manager = $this->settings->managerEmail();
            if ($manager !== null) {
                Mail::to($manager)->queue(new ReservationReceived($reservation));
            }
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        $reservation->forceFill(['email_sent' => true])->save();

        return true;
    }

    /**
     * Conferma di cancellazione al cliente e avviso al gestore. Restituisce false se l'invio non parte.
     */
    public function cancelled(Reservation $reservation): bool
    {
        try {
            Mail::to($reservation->owner()->email)->queue(new ReservationCancelled(
                $reservation,
                $this->settings->brandName(),
                $this->settings->contactPhone(),
            ));

            $manager = $this->settings->managerEmail();
            if ($manager !== null) {
                Mail::to($manager)->queue(new ReservationCancelledNotice($reservation));
            }
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    /**
     * Pagina di cancellazione dell'interfaccia (config engine.cancellation_url, con il segnaposto {token}).
     */
    public static function cancellationUrl(Reservation $reservation): ?string
    {
        $template = config('engine.cancellation_url');

        return is_string($template) && $template !== ''
            ? str_replace('{token}', $reservation->cancellation_token, $template)
            : null;
    }
}
