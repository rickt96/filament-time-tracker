<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class PageVisit extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['last_opened_at' => 'datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function visitable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Registra l'apertura di un record da parte dell'utente loggato.
     *
     * ponytail: un updateOrCreate per apertura, a ritmo umano è irrilevante;
     * se la tabella diventa calda passa a job in coda o buffer.
     */
    public static function recordVisit(?Model $record): void
    {
        if (! Auth::check() || ! $record?->exists) {
            return;
        }

        static::query()->updateOrCreate(
            [
                'user_id' => Auth::id(),
                'visitable_type' => $record->getMorphClass(),
                'visitable_id' => $record->getKey(),
            ],
            ['last_opened_at' => now()],
        );
    }
}
