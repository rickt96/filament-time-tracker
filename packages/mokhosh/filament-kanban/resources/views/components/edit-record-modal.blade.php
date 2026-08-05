{{--
    Filament v5 compatibility: the `<x-filament-panels::form>` wrapper this view
    used to have is a v3 component and no longer exists (nor does
    `<x-filament::form>` — `filament/support` ships no `form` Blade component).

    In v5 `<x-filament::modal>` renders its own `<form>` element as soon as it
    receives a `wire:submit.prevent` attribute, so the modal is now the outermost
    element instead of being nested inside a form.
--}}
<x-filament::modal
    id="kanban--edit-record-modal"
    :slideOver="$this->getEditModalSlideOver()"
    :width="$this->getEditModalWidth()"
    wire:submit.prevent="editModalFormSubmitted"
>
    <x-slot name="header">
        <x-filament::modal.heading>
            {{ $this->getEditModalTitle() }}
        </x-filament::modal.heading>
    </x-slot>

    {{ $this->form }}

    <x-slot name="footer">
        <x-filament::button type="submit">
            {{ $this->getEditModalSaveButtonLabel() }}
        </x-filament::button>

        {{-- `close()` comes from the modal's own Alpine component; poking at
             `isOpen` directly (as the v3 view did) skips the close transition. --}}
        <x-filament::button color="gray" x-on:click="close()">
            {{ $this->getEditModalCancelButtonLabel() }}
        </x-filament::button>
    </x-slot>
</x-filament::modal>
