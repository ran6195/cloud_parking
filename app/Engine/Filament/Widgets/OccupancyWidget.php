<?php

namespace App\Engine\Filament\Widgets;

use App\Engine\Domain\Availability\AvailabilityData;
use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Models\BlockedDate;
use App\Engine\Settings\EngineSettings;
use Filament\Widgets\Widget;

/**
 * Occupazione dei prossimi 14 giorni rispetto ai posti totali, al posto coperto e a Tieni le chiavi.
 */
class OccupancyWidget extends Widget
{
    public const DAYS = 14;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'engine::filament.occupancy';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $from = app(Clock::class)->now()->setTimezone(new \DateTimeZone('Europe/Rome'))->setTime(0, 0);
        $to = $from->modify('+'.(self::DAYS - 1).' days');
        $settings = app(EngineSettings::class);
        $rules = $settings->capacityRules();

        $occupancy = app(AvailabilityData::class)->occupancy($from, $to, [ExtraId::CoveredParking, ExtraId::KeyDrop]);
        $blocked = BlockedDate::query()->whereBetween('date', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get()->map(fn (BlockedDate $d) => $d->date->format('Y-m-d'))->all();

        $rows = [];
        for ($day = $from; $day <= $to; $day = $day->modify('+1 day')) {
            $date = $day->format('Y-m-d');
            $rows[] = [
                'date' => $day,
                'blocked' => in_array($date, $blocked, true),
                'parking' => $occupancy->parkingOn($date),
                'covered' => $occupancy->extraOn(ExtraId::CoveredParking, $date),
                'keys' => $occupancy->extraOn(ExtraId::KeyDrop, $date),
            ];
        }

        return [
            'rows' => $rows,
            'totalSpots' => $settings->totalSpots(),
            'coveredCapacity' => $rules->capacityFor(ExtraId::CoveredParking),
            'keysCapacity' => $rules->capacityFor(ExtraId::KeyDrop),
        ];
    }
}
