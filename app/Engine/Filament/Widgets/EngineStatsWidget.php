<?php

namespace App\Engine\Filament\Widgets;

use App\Engine\Domain\Availability\AvailabilityData;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Models\Reservation;
use App\Engine\Settings\EngineSettings;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

/**
 * La giornata in breve: auto in parcheggio, consegne, ritiri, pagamenti e rimborsi in sospeso.
 */
class EngineStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $now = app(Clock::class)->now();
        $today = $now->setTimezone(new \DateTimeZone('Europe/Rome'))->setTime(0, 0);
        $date = $today->format('Y-m-d');
        $settings = app(EngineSettings::class);

        $occupied = app(AvailabilityData::class)->occupancy($today, $today, [])->parkingOn($date);
        $active = fn (): Builder => Reservation::query()->where(fn (Builder $q) => $q
            ->whereIn('status', ReservationStatus::valid())
            ->orWhere(fn (Builder $q) => $q->where('status', ReservationStatus::PendingPayment)->where('payment_due_by', '>', $now->format(DATE_ATOM))));

        return [
            Stat::make('In parcheggio oggi', "{$occupied} / {$settings->totalSpots()}")
                ->description($settings->autoBlock() ? 'Blocco automatico attivo' : 'Blocco automatico spento'),
            Stat::make('Consegne oggi', (string) $active()->where('start_date', $date)->count()),
            Stat::make('Ritiri oggi', (string) $active()->where('end_date', $date)->count()),
            Stat::make('In attesa di pagamento', (string) Reservation::query()->where('status', ReservationStatus::PendingPayment)->count()),
            Stat::make('Rimborsi da fare', (string) Reservation::query()->whereIn('refund->status', ['requested', 'failed'])->count())
                ->color('warning'),
        ];
    }
}
