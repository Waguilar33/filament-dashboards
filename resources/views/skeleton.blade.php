@php
    use Illuminate\Support\HtmlString;
    use Waguilar\FilamentDashboards\FilamentDashboardsIcon;

    $bar = fn (string $class): HtmlString => new HtmlString('<span class="' . $class . '">&nbsp;</span>');
@endphp

<div class="fi-dashboards-skeleton" aria-hidden="true">
    <x-filament::section
        :heading="$header['heading'] ? $bar('fi-dashboards-skeleton-heading') : null"
        :description="$header['description'] ? $bar('fi-dashboards-skeleton-description') : null"
    >
        @if ($header['action'])
            <x-slot name="afterHeader">
                <x-filament::icon-button
                    :icon="FilamentDashboardsIcon::get(FilamentDashboardsIcon::WIDGET_DATES)"
                    color="gray"
                    disabled
                    tabindex="-1"
                    class="fi-dashboards-skeleton-action"
                />
            </x-slot>
        @endif

        <div class="fi-dashboards-skeleton-body" data-shape="{{ $shape }}">
            @if ($shape === 'line')
                <svg viewBox="0 0 100 100" preserveAspectRatio="none">
                    <polyline points="0,70 15,52 30,60 45,35 60,45 75,22 90,30 100,18" vector-effect="non-scaling-stroke" />
                </svg>
            @endif
        </div>
    </x-filament::section>
</div>
