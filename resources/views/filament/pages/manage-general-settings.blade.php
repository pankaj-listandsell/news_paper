<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            {{-- wire:target tells Filament which action to show the spinner for --}}
            <x-filament::button
                type="submit"
                wire:target="save"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="save">Save settings</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
