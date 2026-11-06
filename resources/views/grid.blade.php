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
    x-data="dashboardGrid({ editable: @js($editable), printing: @js($printing), options: @js(\Waguilar\FilamentDashboards\FilamentDashboardsPlugin::get()->getGridOptions()) })"
    class="fi-dashboards-grid"
    data-editable="{{ $editable ? 'true' : 'false' }}"
>
    <style>
        @media print {
            @page {
                size: {{ \Waguilar\FilamentDashboards\FilamentDashboardsPlugin::get()->getPrintPageSize() }};
            }
        }
    </style>

    @if ($printRange)
        <p class="fi-dashboards-print-range">
            {{ \Waguilar\FilamentDashboards\FilamentDashboardsPlugin::get()->formatDate($printRange->start, 'range') }} – {{ \Waguilar\FilamentDashboards\FilamentDashboardsPlugin::get()->formatDate($printRange->end, 'range') }}
        </p>
    @endif

    <div x-ref="grid" class="grid-stack">
        @foreach ($items as $item)
            <div
                class="grid-stack-item"
                gs-id="{{ $item['id'] }}"
                gs-x="{{ $item['x'] }}"
                gs-y="{{ $item['y'] }}"
                gs-w="{{ $item['w'] }}"
                gs-h="{{ $item['h'] }}"
                @if ($item['adjustableHeight'])
                    gs-resize-handles="e,w,s"
                    gs-min-h="{{ \Waguilar\FilamentDashboards\Actions\SaveDashboardLayout::MIN_FIXED_HEIGHT }}"
                    gs-max-h="{{ \Waguilar\FilamentDashboards\Actions\SaveDashboardLayout::MAX_HEIGHT }}"
                    data-adjustable-height
                @endif
                @if ($item['followsDates'])
                    data-follows-dates
                @endif
                @if ($item['fixedHeight'])
                    data-fixed-height
                @endif
            >
                <div class="grid-stack-item-content">
                    <div class="fi-dashboards-item">
                    @if ($editable)
                        <x-filament::section compact :heading="$item['label']" class="fi-dashboards-frame">
                            @livewire($item['widget'], $item['props'], key('dashboard-item-' . $item['id'] . '-' . $item['version']))

                            <x-slot name="footer">
                                <div class="fi-dashboards-item-actions">
                                    @foreach ([
                                        'editWidget' => filled($item['builderWidgetId']) ? "{ widget: {$item['builderWidgetId']}, item: {$item['id']} }" : null,
                                        'widgetHistory' => filled($item['builderWidgetId']) ? "{ widget: {$item['builderWidgetId']} }" : null,
                                        'fitItemHeight' => $item['adjustableHeight'] ? "{ item: {$item['id']} }" : null,
                                        'duplicateItem' => $item['duplicable'] ? "{ item: {$item['id']} }" : null,
                                        'removeItem' => "{ item: {$item['id']} }",
                                    ] as $name => $arguments)
                                        @if ($arguments && ($tool = $toolbar[$name] ?? null))
                                            <x-filament::link
                                                tag="button"
                                                :color="$tool['color']"
                                                :icon="$tool['icon']"
                                                @class(['fi-dashboards-fit-height' => $name === 'fitItemHeight'])
                                                wire:click="mountAction('{{ $name }}', {{ $arguments }})"
                                            >
                                                {{ $tool['label'] }}
                                            </x-filament::link>
                                        @endif
                                    @endforeach
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
