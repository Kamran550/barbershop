<?php

namespace Database\Factories;

use App\Models\Barber;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Service>
 */
class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'barber_id' => Barber::factory(),
            'name' => 'Xidmət '.fake()->unique()->numberBetween(1, 99999),
            // Slug addan yaranır; eyni adlı xidmətlər (müxtəlif bərbərlərdə) toqquşmasın deyə qısa suffix
            'slug' => fn (array $attributes) => Str::slug($attributes['name']).'-'.Str::lower(Str::random(5)),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([10, 15, 20, 25, 30]),
            'duration_minutes' => fake()->randomElement([20, 30, 45, 60]),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
