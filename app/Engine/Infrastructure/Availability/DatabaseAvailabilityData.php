<?php

namespace App\Engine\Infrastructure\Availability;

use App\Engine\Domain\Availability\AvailabilityData;
use App\Engine\Domain\Availability\DailyOccupancy;
use App\Engine\Domain\Booking\ReservationStatus;
use App\Engine\Domain\Shared\Clock;
use App\Engine\EngineServiceProvider;
use App\Engine\Models\BlockedDate;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Disponibilità dal database con una sola query aggregata per intervallo:
 * per ogni giorno conta le prenotazioni che occupano un posto
 * (start_date <= giorno < end_date), in totale e per ciascun servizio richiesto.
 *
 * Occupano un posto le prenotazioni non cancellate in stato valido e quelle in
 * attesa del pagamento online fino al termine di pagamento
 * (stessa regola di ReservationStatus::holdsCapacity()).
 */
final readonly class DatabaseAvailabilityData implements AvailabilityData
{
    public function __construct(private Clock $clock) {}

    public function blockedDates(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $blocked = BlockedDate::query()
            ->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderBy('date')
            ->get();

        return array_values(array_map(fn (BlockedDate $day) => $day->date->format('Y-m-d'), $blocked->all()));
    }

    public function occupancy(DateTimeImmutable $from, DateTimeImmutable $to, array $extras): DailyOccupancy
    {
        $valid = array_map(fn (ReservationStatus $status) => $status->value, ReservationStatus::valid());
        $statusPlaceholders = implode(', ', array_fill(0, count($valid), '?'));

        $extraColumns = '';
        $extraBindings = [];
        foreach ($extras as $i => $extra) {
            $extraColumns .= ", count(r.id) filter (where exists (
                select 1 from reservation_lines l where l.reservation_id = r.id and l.code = ?
            )) as extra_{$i}";
            $extraBindings[] = $extra->value;
        }

        $rows = DB::connection(EngineServiceProvider::CONNECTION)->select(<<<SQL
            with r as (
                select id, start_date, end_date
                from reservations
                where deleted_at is null
                  and start_date <= ?::date
                  and end_date > ?::date
                  and (status in ({$statusPlaceholders}) or (status = ? and payment_due_by > ?))
            )
            select to_char(d.day, 'YYYY-MM-DD') as day, count(r.id) as parking{$extraColumns}
            from generate_series(?::date, ?::date, interval '1 day') as d(day)
            left join r on r.start_date <= d.day::date and r.end_date > d.day::date
            group by d.day
            order by d.day
            SQL, [
            $to->format('Y-m-d'),
            $from->format('Y-m-d'),
            ...$valid,
            ReservationStatus::PendingPayment->value,
            $this->clock->now()->format(DATE_ATOM),
            ...$extraBindings,
            $from->format('Y-m-d'),
            $to->format('Y-m-d'),
        ]);

        $parking = [];
        $byExtra = [];
        foreach ($rows as $row) {
            $parking[$row->day] = (int) $row->parking;
            foreach ($extras as $i => $extra) {
                $byExtra[$extra->value][$row->day] = (int) $row->{"extra_{$i}"};
            }
        }

        return new DailyOccupancy($parking, $byExtra);
    }
}
