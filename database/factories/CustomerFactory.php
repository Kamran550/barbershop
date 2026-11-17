<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Normalizə olunmuş AZ mobil formatı: +994 + operator kodu + 7 rəqəm
            'phone' => '+994'.fake()->randomElement(['50', '51', '55', '70', '77', '99'])
                .fake()->unique()->numerify('#######'),
        ];
    }
}
