<div
    class="fi-dashboards-stat @if ($hasDrillDown()) fi-dashboards-drill-down @endif"
    data-card-key="{{ $getCardKey() }}"
    @if ($hasDrillDown())
        role="button"
        tabindex="0"
        wire:click="mountAction('drillDown', { card: @js($getCardKey()) })"
        wire:keydown.enter="mountAction('drillDown', { card: @js($getCardKey()) })"
    @endif
>
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
