@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<div
    wire:key="dashboard-grid-{{ $signature }}"
    wire:ignore
    wire:loading.class="fi-dashboards-loading"
    wire:target="filters"
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('dashboard-grid', 'waguilar33/filament-dashboards') }}"
    x-data="dashboardGrid({ editable: @js($editable) })"
    class="fi-dashboards-grid"
    data-editable="{{ $editable ? 'true' : 'false' }}"
>
    <div x-ref="grid" class="grid-stack">
        @foreach ($items as $item)
            <div
                class="grid-stack-item"
                gs-id="{{ $item['id'] }}"
                gs-x="{{ $item['x'] }}"
                gs-y="{{ $item['y'] }}"
                gs-w="{{ $item['w'] }}"
                gs-h="{{ $item['h'] }}"
            >
                <div class="grid-stack-item-content">
                    <div class="fi-dashboards-item">
                    @if ($editable)
                        <x-filament::section compact :heading="$item['label']" class="fi-dashboards-frame">
                            @livewire($item['widget'], $item['props'], key('dashboard-item-' . $item['id'] . '-' . $item['version']))

                            <x-slot name="footer">
                                <div class="fi-dashboards-item-actions">
                                    @if (filled($item['builderWidgetId']))
                                        <x-filament::link
                                            tag="button"
                                            :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare"
                                            wire:click="mountAction('editWidget', { widget: {{ $item['builderWidgetId'] }} })"
                                        >
                                            {{ __('filament-dashboards::dashboards.actions.edit') }}
                                        </x-filament::link>
                                    @endif

                                    <x-filament::link
                                        tag="button"
                                        :icon="\Filament\Support\Icons\Heroicon::OutlinedDocumentDuplicate"
                                        wire:click="mountAction('duplicateItem', { item: {{ $item['id'] }} })"
                                    >
                                        {{ __('filament-dashboards::dashboards.actions.duplicate') }}
                                    </x-filament::link>

                                    <x-filament::link
                                        tag="button"
                                        color="danger"
                                        :icon="\Filament\Support\Icons\Heroicon::OutlinedTrash"
                                        wire:click="mountAction('removeItem', { item: {{ $item['id'] }} })"
                                    >
                                        {{ __('filament-dashboards::dashboards.actions.remove') }}
                                    </x-filament::link>
                                </div>
                            </x-slot>
                        </x-filament::section>
                    @else
                        @livewire($item['widget'], $item['props'], key('dashboard-item-' . $item['id'] . '-' . $item['version']))
                    @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
