@php
    use Filament\Support\Facades\FilamentAsset;
@endphp

<div
    wire:key="dashboard-grid-{{ $signature }}"
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('dashboard-grid', 'waguilar33/filament-dashboards') }}"
    x-data="dashboardGrid({ editable: @js($editable) })"
    class="fi-dashboards-grid"
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
                        <div class="fi-dashboards-toolbar">
                            <span class="fi-dashboards-drag-handle" title="{{ __('filament-dashboards::dashboards.actions.drag') }}">
                                {{ \Filament\Support\generate_icon_html(\Filament\Support\Icons\Heroicon::OutlinedArrowsPointingOut) }}
                            </span>

                            <span class="fi-dashboards-toolbar-actions">
                            @if (filled($item['builderWidgetId']))
                                <x-filament::icon-button
                                    color="gray"
                                    :icon="\Filament\Support\Icons\Heroicon::OutlinedPencilSquare"
                                    :label="__('filament-dashboards::dashboards.actions.edit')"
                                    size="sm"
                                    wire:click="mountAction('editWidget', { widget: {{ $item['builderWidgetId'] }} })"
                                />
                            @endif

                            <x-filament::icon-button
                                color="danger"
                                :icon="\Filament\Support\Icons\Heroicon::OutlinedTrash"
                                :label="__('filament-dashboards::dashboards.actions.remove')"
                                size="sm"
                                wire:click="removeDashboardItem({{ $item['id'] }})"
                            />
                            </span>
                        </div>
                    @endif

                    @livewire($item['widget'], $item['props'], key('dashboard-item-' . $item['id'] . '-' . $item['version']))
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
