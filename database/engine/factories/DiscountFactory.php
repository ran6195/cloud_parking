<?php

namespace Database\Engine\Factories;

use App\Engine\Domain\Pricing\Discount\DiscountType;
use App\Engine\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SCONTO-####')),
            'type' => DiscountType::Percentage,
            'percentage' => 10,
            'amount_cents' => null,
            'max_uses' => null,
            'valid_until' => null,
        ];
    }

    public function fixed(int $cents): static
    {
        return $this->state(['type' => DiscountType::FixedAmount, 'percentage' => null, 'amount_cents' => $cents]);
    }
}
