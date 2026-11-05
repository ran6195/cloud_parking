<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Http;

/**
 * Risposte finte dell'API del motore per i test del CMS (che lo usa solo via HTTP).
 */
trait FakesEngineApi
{
    protected const ENGINE = 'https://motore.test/api/v1';

    protected function configureEngine(): void
    {
        config(['services.engine.url' => self::ENGINE, 'services.engine.key' => 'sk_test']);
        Http::preventStrayRequests();
    }

    /**
     * @return array<string, mixed>
     */
    protected function engineQuote(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => '0193c4d2-7b1e-7cc4-9a51-3f0d2a8e6b10',
            'status' => 'active',
            'expires_at' => '2026-10-01T10:30:00+02:00',
            'stay' => ['start' => '2026-10-01T06:00:00+02:00', 'end' => '2026-10-06T22:00:00+02:00', 'duration_minutes' => 8160, 'billable_days' => 6, 'days_after_30' => 0],
            'extras' => [],
            'payment_method' => 'on_site',
            'lines' => [
                ['type' => 'parking', 'code' => 'parking', 'description' => 'Parcheggio scoperto', 'quantity' => 6, 'unit_price' => null, 'total' => ['amount_cents' => 4200, 'currency' => 'EUR']],
                ['type' => 'payment_fee', 'code' => 'on_site', 'description' => 'Pagamento in parcheggio', 'quantity' => 1, 'unit_price' => ['amount_cents' => 100, 'currency' => 'EUR'], 'total' => ['amount_cents' => 100, 'currency' => 'EUR']],
            ],
            'subtotal' => ['amount_cents' => 4300, 'currency' => 'EUR'],
            'discount' => null,
            'total' => ['amount_cents' => 4300, 'currency' => 'EUR'],
            'bookable' => true,
            'availability' => ['available' => true, 'parking' => ['available' => true, 'reason' => null, 'unavailable_dates' => []], 'extras' => []],
            'cancellation' => ['free_until' => '2026-09-30T06:00:00+02:00'],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function engineReservation(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => '0193c4d5-1a2b-7d3e-8f40-5a6b7c8d9e0f',
            'number' => 22171,
            'status' => 'confirmed',
            'channel' => 'cms',
            'stay' => ['start' => '2026-10-01T06:00:00+02:00', 'end' => '2026-10-06T22:00:00+02:00', 'duration_minutes' => 8160, 'billable_days' => 6, 'days_after_30' => 0],
            'total' => ['amount_cents' => 4300, 'currency' => 'EUR'],
            'payment' => ['method' => 'on_site', 'status' => 'due_on_site', 'amount' => ['amount_cents' => 4300, 'currency' => 'EUR'], 'due_by' => null, 'paid_at' => null],
            'cancellation' => ['free_until' => '2026-09-30T06:00:00+02:00', 'cancelled_at' => null],
            'notifications' => ['email_sent' => true],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function engineProblem(int $status, string $code, string $detail, array $extra = []): array
    {
        return ['type' => 'https://docs.example/errors/'.$code, 'title' => $code, 'status' => $status, 'code' => $code, 'detail' => $detail] + $extra;
    }

    /**
     * @return array<string, mixed>
     */
    protected function engineExtras(): array
    {
        return ['data' => [
            ['id' => 'covered_parking', 'name' => 'Posto coperto', 'description' => null, 'pricing' => 'percentage_of_parking', 'price' => null, 'percentage' => 40, 'capacity_limited' => true, 'incompatible_with' => ['key_drop'], 'message' => null],
            ['id' => 'key_drop', 'name' => 'Tieni le chiavi', 'description' => null, 'pricing' => 'fixed', 'price' => ['amount_cents' => 700, 'currency' => 'EUR'], 'percentage' => null, 'capacity_limited' => true, 'incompatible_with' => ['covered_parking'], 'message' => null],
            ['id' => 'ev_charging', 'name' => 'Ricarica', 'description' => null, 'pricing' => 'informational', 'price' => null, 'percentage' => null, 'capacity_limited' => false, 'incompatible_with' => [], 'message' => 'Costo a kWh'],
        ]];
    }
}
