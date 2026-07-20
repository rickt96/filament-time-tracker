<?php

namespace App\Models;

use App\Enums\WorkPackageStatus;
use Database\Factories\WorkPackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $description
 * @property string|null $budget_hours
 * @property string|null $hourly_rate
 * @property int $sort_order
 * @property WorkPackageStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['project_id', 'name', 'description', 'budget_hours', 'hourly_rate', 'sort_order', 'status'])]
class WorkPackage extends Model
{
    /** @use HasFactory<WorkPackageFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => WorkPackageStatus::class,
            'budget_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * The hourly rate to apply to new Time Entries under this Work Package:
     * its own rate if set, otherwise the parent Project's rate.
     */
    public function effectiveHourlyRate(): ?string
    {
        return $this->hourly_rate ?? $this->project->hourly_rate;
    }
}
