<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Barber>
 */
class BarberFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->firstNameMale();

        return [
            'user_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'title' => 'Bərbər',
            'bio' => fake()->sentence(12),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** Login hesabı olan bərbər. */
    public function withUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory()->barber()->state(['name' => $attributes['name']]),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
