<?php

namespace Database\Engine\Factories;

use App\Engine\Models\BlockedDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlockedDate>
 */
class BlockedDateFactory extends Factory
{
    protected $model = BlockedDate::class;

    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('2026-11-01', '2027-12-31')->format('Y-m-d'),
            'reason' => null,
        ];
    }
}
