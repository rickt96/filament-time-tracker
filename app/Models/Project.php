<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $client_id
 * @property string $name
 * @property string|null $description
 * @property string|null $note
 * @property string|null $color
 * @property ProjectStatus $status
 * @property ProjectVisibility $visibility
 * @property string|null $budget_hours
 * @property string|null $hourly_rate
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['workspace_id', 'client_id', 'name', 'description', 'note', 'color', 'status', 'visibility', 'budget_hours', 'hourly_rate'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'visibility' => ProjectVisibility::class,
            'budget_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withTimestamps();
    }

    /**
     * @return HasMany<WorkPackage, $this>
     */
    public function workPackages(): HasMany
    {
        return $this->hasMany(WorkPackage::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    /**
     * @return BelongsToMany<Invoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_project')
            ->withTimestamps();
    }

    /**
     * Tasks don't belong to a Project directly, only through a Work
     * Package — this aggregates them across all of the Project's Work
     * Packages for display purposes (see ProjectResource's Tasks relation
     * manager, which is read/edit only for the same reason).
     *
     * @return HasManyThrough<Task, WorkPackage, $this>
     */
    public function tasks(): HasManyThrough
    {
        return $this->hasManyThrough(Task::class, WorkPackage::class);
    }

    /**
     * @return MorphToMany<User, $this>
     */
    public function favoritedBy(): MorphToMany
    {
        return $this->morphToMany(User::class, 'favoritable', 'favorites')->withTimestamps();
    }

    /**
     * Non-archived projects, eligible to receive new time entries.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function selectable(Builder $query): void
    {
        $query->where('status', ProjectStatus::Active);
    }
}
