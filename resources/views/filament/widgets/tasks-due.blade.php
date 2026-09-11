<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Task in scadenza</x-slot>

        @forelse ($this->getGroups() as $group)
            <div class="mb-6 last:mb-0">
                <h3 class="mb-2 text-sm font-semibold text-gray-500 dark:text-gray-400">
                    {{ $group['label'] }}
                </h3>

                <div class="space-y-2">
                    @foreach ($group['tasks'] as $task)
                        <a
                            href="{{ $this->editUrlFor($task) }}"
                            @class([
                                'flex items-center justify-between gap-4 rounded-lg border bg-white p-3 shadow-sm transition hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-white/5',
                                'border-gray-200 dark:border-white/10' => ! $group['isOverdue'],
                                'border-y-gray-200 border-r-gray-200 border-l-4 border-l-danger-600 dark:border-y-white/10 dark:border-r-white/10' => $group['isOverdue'],
                            ])
                        >
                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <x-filament::badge :color="$task->status->getColor()">
                                        {{ $task->status->getLabel() }}
                                    </x-filament::badge>

                                    @if ($task->workPackage?->project)
                                        <span class="truncate">
                                            {{ $task->workPackage->project->name }} &rsaquo; {{ $task->workPackage->name }}
                                        </span>
                                    @endif
                                </div>

                                <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $task->name }}
                                </p>
                            </div>

                            <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                {{ $task->expire->translatedFormat('d/m H:i') }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">Nessun task in scadenza</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
