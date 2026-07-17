<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Models\Client;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Project $project): void {
            if ($project->client_id) {
                $project->workspace_id = Client::find($project->client_id)?->workspace_id;
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'client_id' => Client::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->boolean(50) ? fake()->sentence() : null,
            'color' => fake()->hexColor(),
            'status' => ProjectStatus::Active,
            'visibility' => ProjectVisibility::Public,
            'budget_hours' => fake()->boolean(60) ? fake()->randomFloat(2, 10, 500) : null,
            'hourly_rate' => fake()->boolean(60) ? fake()->randomFloat(2, 20, 200) : null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProjectStatus::Archived,
        ]);
    }
}
