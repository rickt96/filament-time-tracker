<?php

namespace App\Filament\Support;

use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Task;
use Closure;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Colors\Color;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared "click the task name" action: instead of following task.url
 * directly, it opens a read-only Infolist modal with the task's basic
 * details, with footer actions to jump to the full edit page or open the
 * external url.
 *
 * Reused across every table that shows a Task's name — TasksTable, both
 * TasksRelationManagers (where the row record already is the Task), and the
 * TimeEntry tables (where the row record is a TimeEntry instead, one step
 * removed from the Task) — $resolveTask bridges that difference.
 */
class TaskDetailsAction
{
    public static function make(?Closure $resolveTask = null): Action
    {
        $resolveTask ??= fn (Model $record): ?Task => $record instanceof Task ? $record : null;

        return Action::make('viewTaskDetails')
            ->label('')
            ->modalHeading(fn (Model $record): string => $resolveTask($record)?->name ?? 'Task')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Chiudi')
            ->schema(fn (Model $record): array => [
                TextEntry::make('project')
                    ->inlineLabel()
                    ->label('Progetto')
                    ->state(fn (): string => $resolveTask($record)?->workPackage?->project?->name ?? '—')
                    ->badge()
                    ->color(
                        fn () => $resolveTask($record)?->workPackage?->project?->color
                            ? Color::hex($resolveTask($record)?->workPackage?->project?->color)
                            : null
                    ),
                TextEntry::make('workPackage')
                    ->inlineLabel()
                    ->label('Work Package')
                    ->state(fn (): string => $resolveTask($record)?->workPackage?->name ?? '—'),
                TextEntry::make('status')
                    ->inlineLabel()
                    ->label('Stato')
                    ->state(fn () => $resolveTask($record)?->status)
                    ->badge(),
                TextEntry::make('priority')
                    ->inlineLabel()
                    ->label('Priorità')
                    ->state(fn () => $resolveTask($record)?->priority)
                    ->badge(),
                TextEntry::make('assignee')
                    ->inlineLabel()
                    ->label('Assegnatario')
                    ->state(fn (): string => $resolveTask($record)?->assignee?->name ?? '—'),
                TextEntry::make('expire')
                    ->inlineLabel()
                    ->label('Scadenza')
                    ->state(fn () => $resolveTask($record)?->expire)
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('external_id')
                    ->inlineLabel()
                    ->label('ID esterno')
                    ->state(fn (): ?string => $resolveTask($record)?->external_id)
                    ->placeholder('—'),
                TextEntry::make('description')
                    ->inlineLabel()
                    ->label('Descrizione')
                    ->state(fn (): ?string => $resolveTask($record)?->description)
                    ->html()
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])
            ->extraModalFooterActions(fn (Model $record): array => array_filter([
                filled($resolveTask($record))
                    ? Action::make('editTask')
                        ->label('Modifica task')
                        ->icon('heroicon-o-pencil-square')
                        ->color('gray')
                        ->url(fn (): string => TaskResource::getUrl('edit', ['record' => $resolveTask($record)]))
                    : null,
                filled($resolveTask($record)?->url)
                    ? Action::make('openExternalUrl')
                        ->label('Apri link esterno')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->color('gray')
                        ->url(fn (): ?string => $resolveTask($record)?->url)
                        ->openUrlInNewTab()
                    : null,
            ]));
    }
}
