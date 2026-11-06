<div class="fi-dashboards-stat" data-card-key="{{ $getCardKey() }}">
    @include('filament-widgets::stats-overview-widget.stat')

    @if ($isResizable())
        <div
            class="fi-dashboards-stat-resize"
            role="separator"
            aria-orientation="vertical"
            aria-label="{{ __('filament-dashboards::dashboards.actions.resize_card') }}"
            tabindex="0"
        ></div>
    @endif
</div>
