<div class="fi-dashboards-widget">
    @livewire($item['widget'], $item['props'], key('dashboard-item-' . $item['id'] . '-' . $item['version']))

    @if (in_array($item['skeleton'], ['line', 'bar', 'pie', 'chart'], true))
        @include('filament-dashboards::skeleton', ['shape' => $item['skeleton'], 'header' => $item['skeletonHeader']])
    @endif
</div>
