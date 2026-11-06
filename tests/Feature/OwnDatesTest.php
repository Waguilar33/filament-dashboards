<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\ResolveWidgetRange;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00'));
    $this->actingAs(user());
    FilamentDashboardsPlugin::get()->formatDatesUsing(fn (CarbonInterface $date, string $context): string => $date->format('d/m/Y'));

    DemoOrderFactory::new()->create(['total' => 100, 'ordered_at' => now(), 'customer' => 'Today']);
    DemoOrderFactory::new()->create(['total' => 50, 'ordered_at' => now()->addDays(20), 'customer' => 'In twenty days']);
    DemoOrderFactory::new()->create(['total' => 25, 'ordered_at' => now()->subDays(10), 'customer' => 'Ten days ago']);
});

it('ships periods that look ahead from today', function (string $period, string $range): void {
    $resolved = app(ResolveWidgetRange::class)->handle(['period' => $period]);

    expect($resolved?->start->toDateString().' - '.$resolved?->end->toDateString())->toBe($range);
})->with([
    'next 7 days' => ['next_7d', '2026-11-04 - 2026-11-10'],
    'next 60 days' => ['next_60d', '2026-11-04 - 2027-01-02'],
    'rest of the month' => ['rest_of_month', '2026-11-04 - 2026-11-30'],
    'rest of the year' => ['rest_of_year', '2026-11-04 - 2026-12-31'],
]);

it('resolves fixed dates in either order and refuses them without both ends', function (): void {
    $range = app(ResolveWidgetRange::class)->handle(['period' => 'custom', 'period_start' => '2026-12-22', 'period_end' => '2026-11-14']);

    expect($range?->start->toDateString().' - '.$range?->end->toDateString())->toBe('2026-11-14 - 2026-12-22')
        ->and(fn () => app(ResolveWidgetRange::class)->handle(['period' => 'custom', 'period_start' => '2026-11-14']))->toThrow(InvalidArgumentException::class);
});

it('charts a future range on its own, ignoring the dashboard dates, and says which range it shows', function (): void {
    $chart = Livewire::test(BuilderChartWidget::class, ['previewType' => 'bar', 'previewConfig' => ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => 'next_30d'], 'pageFilters' => ['preset' => 1]])->instance();
    $data = (fn (): array => $this->getData())->call($chart);

    expect($data['labels'])->toHaveCount(30)
        ->and(array_sum($data['datasets'][0]['data']))->toBe(2)
        ->and($chart->getDescription())->toBe('04/11/2026 – 03/12/2026');
});

it('keeps a chart on the dashboard dates without a range label', function (): void {
    $chart = Livewire::test(BuilderChartWidget::class, ['previewType' => 'bar', 'previewConfig' => ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => 'dashboard'], 'pageFilters' => ['preset' => 1]])->instance();

    expect($chart->getDescription())->toBeNull();
});

it('lists rows within fixed dates and shows the range', function (): void {
    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-11-10', 'period_end' => '2026-12-31'], 'pageFilters' => ['preset' => 1]])
        ->assertSee('In twenty days')
        ->assertDontSee('Ten days ago')
        ->assertSee('10/11/2026 – 31/12/2026');
});

it('shows a stats card on fixed dates with its range', function (): void {
    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => [
        ['source' => 'orders', 'metric' => 'count', 'label' => 'Upcoming', 'dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-11-05', 'period_end' => '2026-12-31'],
    ]]])
        ->assertSeeInOrder(['Upcoming', '1', '05/11/2026 – 31/12/2026']);
});

it('stops following the dashboard dates on its own range', function (): void {
    $widget = builderWidget(user(), ['type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-11-10', 'period_end' => '2026-12-31']]);

    expect($widget->followsDashboardDates())->toBeFalse();
});

it('builds a widget on fixed dates, refusing an end before the start', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);
    $data = ['name' => 'Upcoming', 'source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-11-14', 'period_end' => '2026-11-01'];

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('buildWidget', data: $data, arguments: ['type' => 'table'])
        ->assertHasActionErrors(['period_end']);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('buildWidget', data: [...$data, 'period_end' => '2026-12-22'], arguments: ['type' => 'table'])
        ->assertHasNoActionErrors();

    expect(BuilderWidget::query()->sole()->config)->toMatchArray(['period' => 'custom', 'period_start' => '2026-11-14', 'period_end' => '2026-12-22']);
});
