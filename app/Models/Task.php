<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $work_package_id
 * @property string $name
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskType|null $type
 * @property TaskPriority $priority
 * @property Carbon|null $expire
 * @property int|null $assignee_id
 * @property string|null $external_id
 * @property string|null $url
 * @property string|null $notion_id
 * @property string|null $import_old_id
 * @property array<int, string>|null $tags
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['work_package_id', 'name', 'description', 'status', 'type', 'priority', 'expire', 'assignee_id', 'external_id', 'url', 'notion_id', 'import_old_id', 'tags'])]
class Task extends Model implements HasMedia
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasFilamentComments, InteractsWithMedia, SoftDeletes;

    public const string MEDIA_COLLECTION_ATTACHMENTS = 'attachments';

    public function registerMediaCollections(): void
    {
        // 'public' disk explicitly, not left to media-library's own MEDIA_DISK
        // env default (which only *happens* to also be 'public') — its root
        // is FILESYSTEM_DISK_LOCAL_ROOT (config/filesystems.php).
        $this->addMediaCollection(self::MEDIA_COLLECTION_ATTACHMENTS)
            ->useDisk('public');
    }

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'type' => TaskType::class,
            'priority' => TaskPriority::class,
            'expire' => 'datetime',
            'tags' => 'array',
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
