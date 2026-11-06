<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Schemas\Components\View;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\ResolveDateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Workbench\App\Filament\Widgets\OrdersOverview;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    $this->travelTo(CarbonImmutable::parse('2026-11-04 15:30:00', 'UTC'));
});

function rangeOf(?array $filters): string
{
    $range = app(ResolveDateRange::class)->handle($filters);

    return $range->start->toDateTimeString().' - '.$range->end->toDateTimeString();
}

function datesDashboard(string $widget): Dashboard
{
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => $widget, 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]);
    test()->actingAs($owner);

    return $dashboard;
}

it('counts each preset back from today, today included', function (int $days, string $start): void {
    expect(rangeOf(['preset' => $days]))->toBe("{$start} 00:00:00 - 2026-11-04 23:59:59");
})->with([
    'today' => [1, '2026-11-04'],
    '15 days' => [15, '2026-10-21'],
    '30 days' => [30, '2026-10-06'],
    '60 days' => [60, '2026-09-06'],
    '90 days' => [90, '2026-08-07'],
]);

it('uses whole days of a custom range, in either order', function (): void {
    expect(rangeOf(['preset' => 'custom', 'startDate' => '2026-01-01', 'endDate' => '2026-01-31']))->toBe('2026-01-01 00:00:00 - 2026-01-31 23:59:59')
        ->and(rangeOf(['preset' => 'custom', 'startDate' => '2026-01-31', 'endDate' => '2026-01-01']))->toBe('2026-01-01 00:00:00 - 2026-01-31 23:59:59');
});

it('reads the dates only for a custom range', function (): void {
    expect(rangeOf(['preset' => 15, 'startDate' => '2026-01-01', 'endDate' => '2026-01-31']))->toBe('2026-10-21 00:00:00 - 2026-11-04 23:59:59')
        ->and(rangeOf(['preset' => null, 'startDate' => '2026-01-01', 'endDate' => '2026-01-31']))->toBe('2026-10-06 00:00:00 - 2026-11-04 23:59:59')
        ->and(rangeOf(['preset' => 'custom', 'startDate' => null, 'endDate' => '2026-01-31']))->toBe('2026-10-06 00:00:00 - 2026-11-04 23:59:59');
});

it('falls back to the default preset for custom dates that are not real days', function (mixed $start): void {
    expect(rangeOf(['preset' => 'custom', 'startDate' => $start, 'endDate' => '2026-01-31']))->toBe('2026-10-06 00:00:00 - 2026-11-04 23:59:59');
})->with(['foo', '2026-02-30', '2026-1-1', 20260101]);

it('keeps a custom range to the last five years before its end', function (): void {
    expect(rangeOf(['preset' => 'custom', 'startDate' => '0001-01-01', 'endDate' => '2026-01-31']))->toBe('2021-01-31 00:00:00 - 2026-01-31 23:59:59');
});

it('falls back to the default preset without filters or with an unknown preset', function (): void {
    expect(rangeOf(null))->toBe('2026-10-06 00:00:00 - 2026-11-04 23:59:59')
        ->and(rangeOf(['preset' => 7]))->toBe('2026-10-06 00:00:00 - 2026-11-04 23:59:59');
});

it('lets the app set the presets, the default and the time zone', function (): void {
    FilamentDashboardsPlugin::get()
        ->datePresets([7, 365])
        ->defaultDatePreset(7)
        ->timezoneUsing(fn (): string => 'Pacific/Kiritimati');

    $range = app(ResolveDateRange::class)->handle(null);

    expect($range->start->toDateTimeString())->toBe('2026-10-30 00:00:00')
        ->and($range->end->toDateTimeString())->toBe('2026-11-05 23:59:59')
        ->and($range->start->getTimezone()->getName())->toBe('Pacific/Kiritimati')
        ->and(rangeOf(['preset' => 365]))->toBe('2025-11-06 00:00:00 - 2026-11-05 23:59:59');
});

it('gives a widget the range of its page filters, or the default on a plain page', function (): void {
    $this->actingAs(user());

    $filtered = Livewire::test(OrdersOverview::class, ['pageFilters' => ['preset' => 15]])->instance()->dateRange();
    $plain = Livewire::test(OrdersOverview::class)->instance()->dateRange();

    expect($filtered->start->toDateString())->toBe('2026-10-21')
        ->and($plain->start->toDateString())->toBe('2026-10-06');
});

it('shows the dates on a dashboard with a widget that reads page filters', function (): void {
    $dashboard = datesDashboard('orders-overview');

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSeeHtml('filtersForm.preset')
        ->assertSeeHtml('wire:loading.class="fi-dashboards-loading"')
        ->assertSet('filters', ['preset' => 30, 'startDate' => null, 'endDate' => null])
        ->assertFormFieldHidden('startDate', 'filtersForm');
});

it('hides the dates when no widget reads page filters', function (): void {
    $dashboard = datesDashboard('signups-chart');

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertDontSeeHtml('filtersForm.preset');
});

it('fills a custom range from the previous preset, keeps it custom and passes the filters to the widgets', function (): void {
    $dashboard = datesDashboard('orders-overview');

    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->set('filters.preset', 15)
        ->assertSet('filters.startDate', null)
        ->set('filters.preset', 'custom')
        ->assertSet('filters.startDate', '2026-10-21')
        ->assertSet('filters.endDate', '2026-11-04')
        ->assertFormFieldVisible('startDate', 'filtersForm')
        ->set('filters.startDate', '2026-10-01')
        ->assertSet('filters.preset', 'custom');

    $grid = $page->instance()->getSchema('content')?->getComponent(fn (mixed $component): bool => $component instanceof View, withHidden: true);

    expect($grid)->toBeInstanceOf(View::class)
        ->and($grid->getViewData()['items'][0]['props']['pageFilters'])->toBe(['preset' => 'custom', 'startDate' => '2026-10-01', 'endDate' => '2026-11-04']);
});

it('clears the dates when a preset is picked again', function (): void {
    $dashboard = datesDashboard('orders-overview');

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->set('filters.preset', 'custom')
        ->set('filters.preset', 60)
        ->assertSet('filters', ['preset' => 60, 'startDate' => null, 'endDate' => null])
        ->assertFormFieldHidden('startDate', 'filtersForm');
});
