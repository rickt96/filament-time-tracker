<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;

/**
 * @property int $id
 * @property int $work_package_id
 * @property string $name
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property Carbon|null $expire
 * @property int|null $assignee_id
 * @property string|null $external_id
 * @property string|null $url
 * @property string|null $import_old_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['work_package_id', 'name', 'description', 'status', 'priority', 'expire', 'assignee_id', 'external_id', 'url', 'import_old_id'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasFilamentComments, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'expire' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkPackage, $this>
     */
    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
