<?php

namespace App\Engine\Http\V1\Controllers;

use App\Engine\Domain\Pricing\ExtraId;
use App\Engine\Domain\Pricing\TariffSource;
use App\Engine\Domain\Shared\Clock;
use App\Engine\Http\V1\Problem;
use App\Engine\Models\PriceList;
use App\Engine\Settings\EngineSettings;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Prezzi "a partire da" per landing e materiale promozionale. Usa lo stesso
 * listino del preventivo, compreso il fallback sugli anni precedenti.
 */
final class TariffSummaryController
{
    /** Durate tipiche mostrate in landing. */
    public const DAYS = [1, 3, 7, 14];

    public function __invoke(Request $request, TariffSource $tariffs, EngineSettings $settings, Clock $clock): JsonResponse
    {
        $request->validate(
            ['date' => ['sometimes', 'date_format:Y-m-d']],
            ['date.date_format' => 'La data deve essere nel formato AAAA-MM-GG.'],
        );

        $timezone = new DateTimeZone('Europe/Rome');
        $date = $request->filled('date')
            ? DateTimeImmutable::createFromFormat('!Y-m-d', $request->string('date')->value(), $timezone)
            : $clock->now()->setTimezone($timezone)->setTime(0, 0);

        if ($date === false) {
            throw ValidationException::withMessages(['date' => 'La data deve essere nel formato AAAA-MM-GG.']);
        }

        $match = $tariffs->lookup($date);
        $priceList = $match === null ? null : PriceList::query()->covering($match->matchedDate->format('Y-m-d'))->first();

        if ($match === null || $priceList === null) {
            return Problem::response(404, 'price_list_not_found', 'Non esiste un listino per il '.$date->format('d/m/Y').'.');
        }

        return response()->json([
            'price_list' => [
                'valid_from' => $priceList->starts_on->format('Y-m-d'),
                'valid_to' => $priceList->ends_on->format('Y-m-d'),
            ],
            'prices' => array_map(fn (int $days) => [
                'days' => $days,
                'price' => $match->table->totalFor($days)->jsonSerialize(),
            ], self::DAYS),
            'price_per_day_after_30' => $match->table->extraDayPrice->jsonSerialize(),
            'covered_parking_percentage' => (int) ($settings->extra(ExtraId::CoveredParking)['percentage'] ?? 0),
        ]);
    }
}
