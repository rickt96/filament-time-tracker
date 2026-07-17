<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'name' => fake()->company(),
            'description' => fake()->boolean(50) ? fake()->sentence() : null,
            'contact_name' => fake()->boolean(70) ? fake()->name() : null,
            'contact_email' => fake()->boolean(70) ? fake()->safeEmail() : null,
            'color' => fake()->hexColor(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
