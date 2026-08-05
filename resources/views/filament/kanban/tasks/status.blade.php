{{--
    A board column. Same structure as `filament-kanban::kanban-status`, plus the
    "load more" row that reveals the cards past `$columnLimit`.

    That row sits deliberately *outside* the `[data-status-id]` element:
    SortableJS treats every child of that element as a draggable card and maps
    `children` to record ids in `onAdd`/`onUpdate`, so a button in there would
    both be draggable and corrupt the ids sent back to the server.
--}}
@props(['status'])

<div class="md:w-[24rem] flex-shrink-0 mb-5 md:min-h-full flex flex-col">
    @include(static::$headerView)

    <div
        data-status-id="{{ $status['id'] }}"
        class="flex flex-col flex-1 gap-2 p-3 bg-gray-200 dark:bg-gray-800 rounded-xl"
    >
        @foreach ($status['records'] as $record)
            @include(static::$recordView)
        @endforeach
    </div>

    @if (($status['hiddenCount'] ?? 0) > 0)
        <div class="mt-2 flex justify-center">
            <x-filament::button
                color="gray"
                icon="heroicon-m-chevron-down"
                size="xs"
                wire:click="loadMore('{{ $status['id'] }}')"
                wire:loading.attr="disabled"
                wire:target="loadMore('{{ $status['id'] }}')"
            >
                Mostra altri {{ min($status['hiddenCount'], $this->columnLimit) }}
                <span class="opacity-60">
                    ({{ count($status['records']) }}/{{ $status['total'] }})
                </span>
            </x-filament::button>
        </div>
    @endif
</div>
