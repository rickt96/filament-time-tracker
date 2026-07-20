<?php

namespace Database\Factories;

use App\Enums\TimeEntryStatus;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = Carbon::instance(fake()->dateTimeBetween('-1 month', 'now'))->setTime(9, 0);
        $durationSeconds = fake()->numberBetween(15, 480) * 60;

        return [
            'user_id' => User::factory(),
            'project_id' => Project::factory(),
            'task_id' => null,
            'work_package_id' => null,
            'description' => fake()->boolean(70) ? fake()->sentence() : null,
            'date' => $startedAt->toDateString(),
            'started_at' => $startedAt,
            'ended_at' => $startedAt->copy()->addSeconds($durationSeconds),
            'duration_seconds' => $durationSeconds,
            'status' => TimeEntryStatus::Completed,
            'hourly_rate' => null,
            'total_amount' => null,
        ];
    }
}
