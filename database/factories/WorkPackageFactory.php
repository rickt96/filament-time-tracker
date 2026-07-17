<?php

namespace Database\Factories;

use App\Enums\WorkPackageStatus;
use App\Models\Project;
use App\Models\WorkPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkPackage>
 */
class WorkPackageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(2, true),
            'description' => fake()->boolean(50) ? fake()->sentence() : null,
            'budget_hours' => fake()->boolean(60) ? fake()->randomFloat(2, 5, 200) : null,
            'hourly_rate' => fake()->boolean(30) ? fake()->randomFloat(2, 20, 200) : null,
            'sort_order' => 0,
            'status' => WorkPackageStatus::Planned,
        ];
    }
}
