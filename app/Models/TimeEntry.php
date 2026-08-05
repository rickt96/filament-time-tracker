<?php

namespace App\Models;

use App\Enums\TimeEntryStatus;
use App\Enums\TimeEntrySyncStatus;
use Database\Factories\TimeEntryFactory;
use Guava\Calendar\Contracts\Eventable;
use Guava\Calendar\ValueObjects\CalendarEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * @property int $id
 * @property int $user_id
 * @property int $project_id
 * @property int|null $task_id
 * @property int|null $work_package_id
 * @property string|null $description
 * @property Carbon $date
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int $duration_seconds
 * @property TimeEntryStatus $status
 * @property string|null $hourly_rate
 * @property string|null $total_amount
 * @property Carbon|null $synced_at
 * @property TimeEntrySyncStatus|null $sync_status
 * @property string|null $sync_error
 * @property string|null $import_old_id
 * @property array<int, string>|null $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Client|null $client
 */
#[Fillable([
    'user_id', 'project_id', 'task_id', 'work_package_id', 'description', 'date', 'started_at', 'ended_at',
    'duration_seconds', 'status', 'hourly_rate', 'total_amount', 'synced_at', 'sync_status', 'sync_error',
    'import_old_id', 'tags',
])]
class TimeEntry extends Model implements Eventable
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'status' => TimeEntryStatus::class,
            'hourly_rate' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'synced_at' => 'datetime',
            'sync_status' => TimeEntrySyncStatus::class,
            'tags' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<WorkPackage, $this>
     */
    public function workPackage(): BelongsTo
    {
        return $this->belongsTo(WorkPackage::class);
    }

    /**
     * @return BelongsToMany<Invoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_time_entry')
            ->withTimestamps();
    }

    /**
     * The Client is derived from the Project — never duplicated on the Time Entry itself.
     *
     * @return Attribute<Client|null, never>
     */
    protected function client(): Attribute
    {
        return Attribute::make(get: fn (): ?Client => $this->project?->client)->shouldCache();
    }

    public function toCalendarEvent(): CalendarEvent
    {
        $primary = $this->task?->name ?? $this->project->name;

        $title = $this->description
            ? new HtmlString(e($primary).'<br>'.e($this->description))
            : $primary;

        return CalendarEvent::make($this)
            ->title($title)
            ->start($this->started_at)
            ->end($this->ended_at ?? $this->started_at)
            ->backgroundColor($this->project->color)
            ->action('edit');
    }
}
