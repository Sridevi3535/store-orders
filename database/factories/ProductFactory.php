<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'code' => strtoupper(fake()->unique()->bothify('PRD-####')),
            'price' => fake()->randomFloat(2, 5, 500),
            'tax_percent' => fake()->randomElement([0, 5, 12, 18]),
            'stock' => fake()->numberBetween(0, 100),
        ];
    }
}