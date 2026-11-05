<?php

namespace Database\Engine\Factories;

use App\Engine\Models\PaymentTransaction;
use App\Engine\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    protected $model = PaymentTransaction::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'provider' => 'paypal',
            'order_id' => strtoupper(fake()->unique()->bothify('#?#?#?#?#?#?#?#?#')),
            'status' => 'COMPLETED',
            'amount_cents' => 2500,
            'currency' => 'EUR',
            'payload' => [],
        ];
    }
}
