<div class="fi-dashboards-stat" data-card-key="{{ $getCardKey() }}">
    @include('filament-widgets::stats-overview-widget.stat')

    @if ($isResizable())
        <div class="fi-dashboards-stat-resize"></div>
    @endif
</div>
