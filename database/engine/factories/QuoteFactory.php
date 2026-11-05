<?php

namespace Database\Engine\Factories;

use App\Engine\Domain\Pricing\PaymentMethod;
use App\Engine\Models\Quote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Preventivo di 3 giorni a 25 € (parcheggio scoperto, pagamento online).
 *
 * @extends Factory<Quote>
 */
class QuoteFactory extends Factory
{
    protected $model = Quote::class;

    public function definition(): array
    {
        return [
            'starts_at' => CarbonImmutable::parse('2026-11-20 08:00', 'Europe/Rome'),
            'ends_at' => CarbonImmutable::parse('2026-11-23 08:00', 'Europe/Rome'),
            'extras' => [],
            'payment_method' => PaymentMethod::Online,
            'lines' => [[
                'type' => 'parking', 'code' => 'parking', 'description' => 'Parcheggio scoperto',
                'quantity' => 3, 'unit_cents' => null, 'total_cents' => 2500,
            ]],
            'subtotal_cents' => 2500,
            'discount_cents' => 0,
            'total_cents' => 2500,
            'discount' => null,
            'trace' => ['price_table' => 'Novembre 2026', 'fallback_years' => 0],
            'expires_at' => CarbonImmutable::now()->addMinutes(30),
        ];
    }
}
