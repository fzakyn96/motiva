<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'parent_id' => null,
            'code' => fake()->unique()->numerify('DEPT###'),
            'name' => fake()->company(),
            'is_active' => true,
        ];
    }

    public function childOf(Department $department): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $department->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
