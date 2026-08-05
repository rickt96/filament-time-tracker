{{--
    Board view for App\Filament\Pages\TasksKanbanBoard.

    Same structure as `filament-kanban::kanban-board`, plus the filter bar.
    The columns keep the package's `wire:ignore.self` wrapper: SortableJS is
    bound to the `[data-status-id]` elements by the scripts view, and Livewire
    must morph that subtree instead of replacing it, or the bindings are lost
    on every re-render (a filter change, a card drop, …).
--}}
<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-col gap-3">
            <form wire:submit.prevent>
                {{ $this->filtersForm }}
            </form>

            @if ($this->hasActiveFilters())
                <div class="flex justify-end">
                    <x-filament::button
                        color="gray"
                        icon="heroicon-m-x-mark"
                        size="sm"
                        wire:click="resetFilters"
                    >
                        Azzera filtri
                    </x-filament::button>
                </div>
            @endif
        </div>
    </x-filament::section>

    <div x-data wire:ignore.self class="md:flex overflow-x-auto overflow-y-hidden gap-4 pb-4">
        @foreach ($statuses as $status)
            @include(static::$statusView)
        @endforeach

        <div wire:ignore>
            @include(static::$scriptsView)
        </div>
    </div>

    @unless ($disableEditModal)
        <x-filament-kanban::edit-record-modal />
    @endunless
</x-filament-panels::page>
