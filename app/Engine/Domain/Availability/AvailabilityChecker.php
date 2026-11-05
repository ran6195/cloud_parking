<?php

namespace App\Engine\Domain\Availability;

use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\ExtrasCatalog;
use App\Engine\Domain\Pricing\StayPeriod;
use DateTimeImmutable;

/**
 * Disponibilità di un periodo, con le regole del legacy (vedi docs/availability.md):
 *
 * - parcheggio: giorni dalla data di consegna alla data di ritiro, **entrambe incluse**;
 *   non disponibile se un giorno è bloccato o, con il blocco automatico, se i posti
 *   occupati raggiungono i posti totali;
 * - servizi a capacità limitata: giorni dalla consegna al ritiro **escluso**;
 *   non disponibile se il servizio è disattivato, ha capacità 0 o è pieno.
 */
final readonly class AvailabilityChecker
{
    public function __construct(
        private AvailabilityData $data,
        private CapacityRules $rules,
        private ExtrasCatalog $catalog,
    ) {}

    /**
     * @param  list<ExtraId>  $extras  servizi richiesti; nel risultato compaiono solo quelli a capacità limitata
     */
    public function check(StayPeriod $stay, array $extras = []): Availability
    {
        $from = $stay->start->setTime(0, 0);
        $to = $stay->end->setTime(0, 0);
        $days = self::days($from, $to);

        $limited = $this->capacityLimited($extras);
        $occupancy = $this->data->occupancy($from, $to, array_values(array_filter(
            $limited,
            fn (ExtraId $id) => $this->isEnabled($id),
        )));

        $results = [];
        foreach ($limited as $id) {
            $results[$id->value] = $this->checkExtra($id, array_slice($days, 0, -1), $occupancy);
        }

        return new Availability($this->checkParking($from, $to, $days, $occupancy), $results);
    }

    /**
     * @param  list<string>  $days
     */
    private function checkParking(DateTimeImmutable $from, DateTimeImmutable $to, array $days, DailyOccupancy $occupancy): AvailabilityItem
    {
        $blocked = $this->data->blockedDates($from, $to);
        if ($blocked !== []) {
            return AvailabilityItem::unavailable(AvailabilityReason::BlockedDate, $blocked);
        }

        if ($this->rules->autoBlock) {
            $full = array_values(array_filter($days, fn (string $day) => $occupancy->parkingOn($day) >= $this->rules->totalSpots));
            if ($full !== []) {
                return AvailabilityItem::unavailable(AvailabilityReason::CapacityFull, $full);
            }
        }

        return AvailabilityItem::available();
    }

    /**
     * @param  list<string>  $days  giorni occupati dalla sosta (ritiro escluso)
     */
    private function checkExtra(ExtraId $id, array $days, DailyOccupancy $occupancy): AvailabilityItem
    {
        if (! $this->isEnabled($id)) {
            return AvailabilityItem::unavailable(AvailabilityReason::ServiceDisabled);
        }

        $capacity = $this->rules->capacityFor($id);
        $full = array_values(array_filter($days, fn (string $day) => $occupancy->extraOn($id, $day) >= $capacity));

        return $full === []
            ? AvailabilityItem::available()
            : AvailabilityItem::unavailable(AvailabilityReason::CapacityFull, $full);
    }

    private function isEnabled(ExtraId $id): bool
    {
        return ($this->catalog->find($id)->active ?? false) && $this->rules->capacityFor($id) > 0;
    }

    /**
     * @param  list<ExtraId>  $extras
     * @return list<ExtraId> senza duplicati, nell'ordine di ExtraId
     */
    private function capacityLimited(array $extras): array
    {
        $limited = [];
        foreach ($extras as $id) {
            if ($this->catalog->find($id)->capacityLimited ?? false) {
                $limited[$id->value] = $id;
            }
        }
        usort($limited, fn (ExtraId $a, ExtraId $b) => $a->position() <=> $b->position());

        return $limited;
    }

    /**
     * @return list<string> date Y-m-d da $from a $to inclusi
     */
    private static function days(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $days = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $days[] = $day->format('Y-m-d');
        }

        return $days;
    }
}
