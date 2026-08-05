{{--
    Column heading. Included by `filament-kanban::kanban-status`, which passes
    down `$status` as ['id' => ..., 'title' => ..., 'records' => [...]].
--}}
@php
    $statusEnum = \App\Enums\TaskStatus::tryFrom($status['id']);

    // The enum speaks in Filament colour names; map them to the palette
    // classes the panel theme compiles.
    $dotClass = match ($statusEnum?->getColor()) {
        'info' => 'bg-info-500',
        'warning' => 'bg-warning-500',
        'success' => 'bg-success-500',
        'danger' => 'bg-danger-500',
        default => 'bg-gray-400',
    };
@endphp

<div class="mb-2 flex items-center justify-between gap-2 px-1">
    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
        <span class="size-2.5 shrink-0 rounded-full {{ $dotClass }}"></span>
        {{ $status['title'] }}
    </h3>

    <span class="rounded-md bg-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
        {{ count($status['records']) }}
    </span>
</div>
