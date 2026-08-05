{{--
    A single Task card. Included by `filament-kanban::kanban-status` with
    `$record` bound to the App\Models\Task.

    Layout, top to bottom: title, project name, tags, external url.

    The `id` attribute is what SortableJS reports back as `recordId`, so it has
    to stay the primary key and nothing else.
--}}
@php
    /** @var \App\Models\Task $record */
    $project = $record->workPackage?->project;
    $workPackage = $record->workPackage;

    // Done and Cancelled have no column (see TaskStatus::kanbanCases()), so
    // every card on the board is still open and a past deadline is overdue.
    $isOverdue = $record->expire?->isPast() ?? false;
@endphp

<div
    id="{{ $record->getKey() }}"
    {{-- The edit modal re-reads the record server side, so there is no need to
         serialise the whole model (description included) into the DOM. --}}
    wire:click="recordClicked('{{ $record->getKey() }}', {})"
    @class([
        'record flex cursor-grab flex-col gap-1 rounded-lg p-3 shadow-sm',
        'bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10' => ! $isOverdue,
        // Filament exposes no bare `danger` shade — only danger-50…950.
        'bg-danger-50 ring-2 ring-danger-600 dark:bg-gray-900 dark:ring-danger-400' => $isOverdue,
    ])
>
    <p class="flex items-start gap-1.5 text-sm font-medium text-gray-950 dark:text-white">
        <span class="min-w-0 flex-1">
            {{ $record->name }}
        </span>

        @if ($isOverdue)
            <x-filament::icon
                icon="heroicon-s-bell-alert"
                title="Scaduto il {{ $record->expire->translatedFormat('d/m/Y') }}"
                class="mt-0.5 size-4 shrink-0 text-danger-600 dark:text-danger-400"
            />
        @endif
    </p>

    @if ($project)
        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
            <span
                class="size-2 shrink-0 rounded-full"
                style="background-color: {{ $project->color ?: '#9ca3af' }}"
            ></span>
            <span class="truncate">
                {{ $project->name }} @if($workPackage) / {{ $workPackage->name }} @endif
            </span>
        </div>
    @endif

    @if (filled($record->tags))
        <div class="flex flex-wrap gap-1">
            @foreach ($record->tags as $tag)
                <span class="rounded-md bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-white/5 dark:text-gray-300">
                    {{ $tag }}
                </span>
            @endforeach
        </div>
    @endif

    @if (filled($record->url))
        {{-- `.stop` keeps the card's own click from also opening the edit modal. --}}
        <a
            href="{{ $record->url }}"
            target="_blank"
            rel="noopener noreferrer"
            x-on:click.stop
            title="{{ $record->url }}"
            class="flex items-center gap-1 text-xs text-gray-400 transition hover:text-primary-600 dark:hover:text-primary-400"
        >
            <x-filament::icon
                icon="heroicon-m-arrow-top-right-on-square"
                class="size-3.5 shrink-0"
            />
            <span class="truncate">{{ $record->url }}</span>
        </a>
    @endif

    {{-- link pagina edit --}}
    <a
        href="{{ App\Filament\Resources\Tasks\TaskResource::getUrl('edit', ['record' => $record->getKey()]) }}"
        target="_blank"
        rel="noopener noreferrer"
        x-on:click.stop
        class="flex items-center gap-1 text-xs text-gray-400 transition hover:text-primary-600 dark:hover:text-primary-400"
    >
        <x-filament::icon
            icon="heroicon-m-pencil-square"
            class="size-3.5 shrink-0"
        />
        <span class="truncate">Modifica</span>
    </a>

</div>
