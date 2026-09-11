<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use SolutionForest\FilamentTree\Concern\ModelTree;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['workspace_id', 'parent_id', 'documentable_type', 'documentable_id', 'title', 'slug', 'content', 'created_by', 'sort_order'])]
class Document extends Model implements HasMedia
{
    use InteractsWithMedia, ModelTree;

    public const string MEDIA_COLLECTION_ATTACHMENTS = 'attachments';

    protected $casts = [
        'parent_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            if (blank($document->slug)) {
                $document->slug = Str::slug($document->title) ?: 'documento';
            }
        });
    }

    public function determineOrderColumnName(): string
    {
        return 'sort_order';
    }

    public function registerMediaCollections(): void
    {
        // 'public' disk esplicito, coerente con Task/Invoice — vedi
        // ModelFolderPathGenerator per il path (documents/{media id}/...).
        $this->addMediaCollection(self::MEDIA_COLLECTION_ATTACHMENTS)
            ->useDisk('public');
    }

    /**
     * @return BelongsTo<Workspace, $this>
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'parent_id');
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Segmento URL permalink: l'id resta l'unica chiave di risoluzione,
     * lo slug non cambia più dopo la creazione anche se il titolo cambia.
     */
    public function urlSegment(): string
    {
        return $this->getKey().($this->slug ? '-'.$this->slug : '');
    }

    public static function resolveKeyFromUrlSegment(string $segment): ?int
    {
        return preg_match('/^(\d+)/', $segment, $matches) ? (int) $matches[1] : null;
    }

    /**
     * @return MorphToMany<User, $this>
     */
    public function favoritedBy(): MorphToMany
    {
        return $this->morphToMany(User::class, 'favoritable', 'favorites')->withTimestamps();
    }
}
