<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Tags are freeform strings stored per-record in a `tags` JSON column
 * (Task, TimeEntry), not a shared relational entity — so a Select's
 * options for filtering/suggesting by tag come from flattening whatever
 * values already exist, not from a lookup table.
 */
class TagOptions
{
    /**
     * @param  Builder<*>  $query  Already scoped to the tenant/records to draw tags from.
     * @return array<string, string>
     */
    public static function from(Builder $query): array
    {
        return $query
            ->pluck('tags')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->mapWithKeys(fn (string $tag): array => [$tag => $tag])
            ->all();
    }
}
