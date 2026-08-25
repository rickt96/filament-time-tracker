<?php

namespace App\Filament\Forms\Components\RichEditor;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor\MentionProvider;
use Illuminate\Database\Eloquent\Builder;

/**
 * "@" mentions of other Tasks inside a RichEditor, scoped to the current
 * tenant. Shared between TaskForm and TasksKanbanBoard's edit modal, since
 * both edit the same Task.description column — the mention's label and url
 * are resolved and baked into the stored HTML when the content is saved
 * (see Filament\Forms\Components\RichEditor\TipTapExtensions\MentionExtension),
 * so every place that already renders description as raw HTML (TaskDetailsAction,
 * ...) shows a working link with no further changes needed there.
 */
class TaskMentionProvider
{
    public static function make(): MentionProvider
    {
        return MentionProvider::make('@')
            ->getSearchResultsUsing(fn (string $search): array => self::query()
                ->where('name', 'like', "%{$search}%")
                ->orderBy('name')
                ->limit(10)
                ->pluck('name', 'id')
                ->all())
            ->getLabelsUsing(fn (array $ids): array => self::query()
                ->whereIn('id', $ids)
                ->pluck('name', 'id')
                ->all())
            ->url(fn (string $id): string => TaskResource::getUrl('edit', ['record' => $id]));
    }

    /**
     * @return Builder<Task>
     */
    private static function query(): Builder
    {
        return Task::query()->whereHas(
            'workPackage.project',
            fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()),
        );
    }
}
