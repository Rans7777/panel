<x-filament::page>
    @php
        $form = (function() {
            return $this->form;
        })();
    @endphp

    {{ $form }}
</x-filament::page>
