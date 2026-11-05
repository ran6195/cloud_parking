<?php

namespace Tests\Engine\Support;

use App\Engine\Domain\Shared\Clock;
use App\Engine\Domain\Shared\FrozenClock;
use App\Engine\Models\ApiClient;
use App\Engine\Models\PriceList;
use Database\Engine\Seeders\EngineSeeder;

/**
 * Supporto per i feature test dell'API: client, orologio fermo, listino di novembre 2026.
 */
final class EngineApi
{
    public const NOW = '2026-11-05 12:00:00 Europe/Rome';

    public const ORIGIN = 'https://sostablq.example';

    public static function freezeClock(string $at = self::NOW): void
    {
        app()->instance(Clock::class, FrozenClock::at($at));
    }

    /**
     * @return array{client: ApiClient, secret_key: string, headers: array<string, string>}
     */
    public static function client(string $channel = 'web', ?int $rateLimit = null): array
    {
        $issued = ApiClient::issue('Client di test', $channel, [self::ORIGIN], $rateLimit);

        return $issued + ['headers' => ['Authorization' => 'Bearer '.$issued['secret_key'], 'Accept' => 'application/json']];
    }

    /**
     * Listino di novembre 2026 del legacy: 1 giorno 17 €, 3 giorni 27 €, 30 giorni 165 €, giorno aggiuntivo 10 €.
     */
    public static function novemberPriceList(): PriceList
    {
        return PriceList::factory()
            ->period('2026-11-01', '2026-11-30')
            ->withPrices(EngineSeeder::SAMPLE_CENTS)
            ->create(['name' => 'Novembre 2026', 'extra_day_cents' => 1000]);
    }
}
