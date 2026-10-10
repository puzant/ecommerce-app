<?php

namespace Database\Factories;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PROMO-##??'),
            'type' => 'percentage',
            'value' => fake()->numberBetween(5, 30),
            'minimum_cart_amount' => 5000,
            'maximum_discount' => 2000,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'usage_limit' => 100,
            'per_customer_usage_limit' => 1,
            'active' => true,
        ];
    }
}
