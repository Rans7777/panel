<x-filament-panels::page.simple>
    <div class="form-container">
        @php /** @var \App\Filament\Pages\Auth\Login $this */ @endphp
        {{ $this->form }}
    </div>
    <x-filament::button type="submit" class="w-full" wire:click="authenticate">
        Sign in
    </x-filament::button>
</x-filament-panels::page.simple>
