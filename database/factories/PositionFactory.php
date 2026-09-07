<?php

namespace Database\Factories;

use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected $model = Position::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('POS###'),
            'name' => fake()->jobTitle(),
            'level' => fake()->numberBetween(1, 10),
            'is_active' => true,
        ];
    }
}