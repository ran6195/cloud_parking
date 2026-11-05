<?php

namespace App\Engine\Filament\Support;

use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\PaymentMethod;

/**
 * Etichette e colori del pannello.
 */
final class Labels
{
    public static function status(ReservationStatus $status): string
    {
        return match ($status) {
            ReservationStatus::PendingPayment => 'In attesa di pagamento',
            ReservationStatus::Confirmed => 'Confermata',
            ReservationStatus::Paid => 'Pagata',
            ReservationStatus::Completed => 'Conclusa',
            ReservationStatus::Cancelled => 'Annullata',
            ReservationStatus::Expired => 'Scaduta',
        };
    }

    public static function statusColor(ReservationStatus $status): string
    {
        return match ($status) {
            ReservationStatus::PendingPayment => 'warning',
            ReservationStatus::Confirmed, ReservationStatus::Paid => 'success',
            ReservationStatus::Completed => 'gray',
            ReservationStatus::Cancelled, ReservationStatus::Expired => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        $labels = [];
        foreach (ReservationStatus::cases() as $status) {
            $labels[$status->value] = self::status($status);
        }

        return $labels;
    }

    public static function paymentMethod(PaymentMethod $method): string
    {
        return $method === PaymentMethod::Online ? 'Online (PayPal)' : 'In parcheggio';
    }

    public static function refund(?string $status): string
    {
        return match ($status) {
            'requested' => 'Da rimborsare',
            'refunded' => 'Rimborsata',
            'failed' => 'Rimborso automatico non riuscito',
            default => '—',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function extras(): array
    {
        return [
            ExtraId::CoveredParking->value => 'Posto coperto',
            ExtraId::ExteriorWash->value => 'Lavaggio esterno',
            ExtraId::InteriorWash->value => 'Pulizia abitacolo',
            ExtraId::KeyDrop->value => 'Tieni le chiavi',
            ExtraId::EvCharging->value => 'Ricarica auto elettrica',
            ExtraId::TyreRepair->value => 'Riparazione gomme',
        ];
    }
}
