<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\WorkPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'work_package_id' => WorkPackage::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->boolean(50) ? fake()->sentence() : null,
            'status' => TaskStatus::Todo,
            'assignee_id' => null,
            'external_id' => null,
        ];
    }

    public function withExternalId(): static
    {
        return $this->state(fn (array $attributes) => [
            'external_id' => fake()->uuid(),
        ]);
    }
}
