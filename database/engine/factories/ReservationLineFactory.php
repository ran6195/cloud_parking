<?php

namespace Database\Engine\Factories;

use App\Engine\Domain\Pricing\QuoteLineType;
use App\Engine\Models\Reservation;
use App\Engine\Models\ReservationLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationLine>
 */
class ReservationLineFactory extends Factory
{
    protected $model = ReservationLine::class;

    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory(),
            'position' => 1,
            'type' => QuoteLineType::Parking,
            'code' => 'parking',
            'description' => 'Parcheggio scoperto',
            'quantity' => 3,
            'unit_cents' => null,
            'total_cents' => 2500,
        ];
    }
}
