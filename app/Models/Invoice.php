<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $client_id
 * @property int $year
 * @property int $number
 * @property string $amount
 * @property InvoiceStatus $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $collected_at
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $label
 */
#[Fillable(['workspace_id', 'client_id', 'year', 'number', 'amount', 'status', 'sent_at', 'collected_at', 'note'])]
class Invoice extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
            'sent_at' => 'date',
            'collected_at' => 'date',
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
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'invoice_project')
            ->withTimestamps();
    }

    /**
     * The Time Entries actually billed on this invoice. A Time Entry can
     * appear on more than one Invoice (e.g. a partial bill followed later by
     * a final one), so this is a many-to-many rather than a time_entries.
     * invoice_id column.
     *
     * @return BelongsToMany<TimeEntry, $this>
     */
    public function timeEntries(): BelongsToMany
    {
        return $this->belongsToMany(TimeEntry::class, 'invoice_time_entry')
            ->withTimestamps();
    }

    /**
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::make(get: fn (): string => "{$this->year}-{$this->number}")->shouldCache();
    }
}
