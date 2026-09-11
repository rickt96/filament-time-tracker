<x-filament-panels::page>
    <div class="flex items-start gap-6">
        <aside class="w-[28rem] shrink-0 grow-0 self-start">
            @livewire(\App\Filament\Resources\Documents\Widgets\DocumentTableWidget::class, [
                'record' => $this->getRecord(),
            ])
        </aside>

        <div class="min-w-0 flex-1">
            @if ($this->selectedDocumentId)
                <form wire:submit="saveDocument" class="fi-section rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
                    {{ $this->documentEditorForm }}

                    <x-filament::button type="submit" class="mt-4">
                        Salva modifiche
                    </x-filament::button>
                </form>
            @else
                <div class="fi-section flex items-center justify-center rounded-xl border border-gray-200 bg-white p-12 text-sm text-gray-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                    <span class="py-12">Seleziona un documento.</span>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
