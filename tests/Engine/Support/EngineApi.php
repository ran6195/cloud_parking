<?php

namespace Tests\Engine\Support;

use App\Engine\Domain\Shared\Clock;
use App\Engine\Domain\Shared\FrozenClock;
use App\Engine\Models\ApiClient;
use App\Engine\Models\PriceList;
use Database\Engine\Seeders\EngineSeeder;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Supporto per i feature test dell'API: client, orologio fermo, listino di novembre 2026.
 */
final class EngineApi
{
    public const NOW = '2026-11-05 12:00:00 Europe/Rome';

    public const ORIGIN = 'https://sostablq.example';

    /**
     * Ferma l'orologio del motore e quello di Laravel (timestamp dei modelli, now()).
     */
    public static function freezeClock(string $at = self::NOW): void
    {
        app()->instance(Clock::class, FrozenClock::at($at));
        Carbon::setTestNow(Carbon::parse($at));
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
     * Crea un preventivo via API (di default: 3 giorni a novembre 2026, posto coperto, in parcheggio) e ne restituisce l'id.
     *
     * @param  array<string, mixed>  $overrides
     * @param  array<string, string>  $headers
     */
    public static function createQuote(TestCase $test, array $headers, array $overrides = []): string
    {
        return $test->postJson('/api/v1/quotes', $overrides + [
            'start' => '2026-11-20T08:00:00+01:00',
            'end' => '2026-11-23T07:00:00+01:00',
            'extras' => ['covered_parking'],
            'payment_method' => 'on_site',
        ], $headers)->assertCreated()->json('id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function reservationBody(string $quoteId, array $overrides = []): array
    {
        return array_replace_recursive([
            'quote_id' => $quoteId,
            'customer' => ['first_name' => 'Maria', 'last_name' => 'Rossi', 'email' => 'maria.rossi@example.com', 'phone' => '+39 333 123 4567'],
            'vehicle' => ['plate' => 'ab 123 cd', 'make' => 'Fiat', 'model' => 'Panda'],
            'travel' => ['outbound_flight' => 'FR1234', 'return_flight' => 'FR1235', 'passengers' => 2],
            'billing' => null,
            'notes' => 'Bagaglio ingombrante.',
            'referral' => 'partner-hotel',
            'consents' => ['privacy' => true, 'marketing' => false],
        ], $overrides);
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
