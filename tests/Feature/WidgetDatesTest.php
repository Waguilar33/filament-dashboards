<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\ApplyWidgetDates;
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
    $this->owner = user();
    $this->actingAs($this->owner);
    FilamentDashboardsPlugin::get()->formatDatesUsing(fn (CarbonInterface $date, string $context): string => $date->format('d/m/Y'));

    DemoOrderFactory::new()->create(['ordered_at' => now(), 'customer' => 'Today']);
    DemoOrderFactory::new()->create(['ordered_at' => now()->addDays(20), 'customer' => 'In twenty days']);
    DemoOrderFactory::new()->create(['ordered_at' => now()->subDays(10), 'customer' => 'Ten days ago']);
});

it('rewrites only the dated parts of a widget to the viewer’s dates', function (): void {
    $config = app(ApplyWidgetDates::class)->handle(['cards' => [
        ['source' => 'orders', 'metric' => 'count', 'dates_field' => 'ordered_at', 'period' => 'month'],
        ['source' => 'orders', 'metric' => 'count'],
    ]], ['preset' => 15]);

    expect($config['cards'])->toBe([
        ['source' => 'orders', 'metric' => 'count', 'dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-10-21', 'period_end' => '2026-11-04'],
        ['source' => 'orders', 'metric' => 'count'],
    ])
        ->and(app(ApplyWidgetDates::class)->handle(['date_field' => 'ordered_at', 'period' => '12m'], ['preset' => 'next_30d']))->toBe(['date_field' => 'ordered_at', 'period' => 'next_30d'])
        ->and(app(ApplyWidgetDates::class)->handle(['dates_field' => 'ordered_at', 'period' => 'month'], ['preset' => 'dashboard']))->toBe(['dates_field' => 'ordered_at', 'period' => 'dashboard'])
        ->and(app(ApplyWidgetDates::class)->handle(['dates_field' => 'ordered_at'], ['preset' => 'custom', 'startDate' => '2026-11-10', 'endDate' => '2026-11-30']))
        ->toBe(['dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-11-10', 'period_end' => '2026-11-30']);
});

it('lets a viewer change one chart’s dates without touching the saved widget or other charts', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Bar, 'config' => ['viewer_dates' => true, 'source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '30d']]);

    $chart = Livewire::test(BuilderChartWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7, 'pageFilters' => ['preset' => 30]])
        ->set('deferredFilters.preset', 'next_30d')
        ->call('applyFilters');

    expect($chart->instance()->getDescription())->toBe('04/11/2026 – 03/12/2026')
        ->and(session('filament-dashboards.widget-dates.7'))->toBe(['preset' => 'next_30d'])
        ->and($widget->fresh()->config['period'])->toBe('30d')
        ->and(Livewire::test(BuilderChartWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 8, 'pageFilters' => ['preset' => 30]])->instance()->getDescription())
        ->toBe('06/10/2026 – 04/11/2026');
});

it('remembers the viewer’s pick for the widget on that dashboard', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Bar, 'config' => ['viewer_dates' => true, 'source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '30d']]);
    session()->put('filament-dashboards.widget-dates.7', ['preset' => 'custom', 'startDate' => '2026-11-10', 'endDate' => '2026-11-30']);

    Livewire::test(BuilderChartWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7])
        ->assertSet('deferredFilters.preset', 'custom')
        ->assertSet('deferredFilters.startDate', '2026-11-10')
        ->assertSee('10/11/2026 – 30/11/2026');
});

it('puts a chart back on its saved dates', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Bar, 'config' => ['viewer_dates' => true, 'source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '30d']]);
    session()->put('filament-dashboards.widget-dates.7', ['preset' => 'next_30d']);

    Livewire::test(BuilderChartWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7])
        ->call('resetFilters')
        ->assertSet('deferredFilters.preset', 'default');

    expect(session()->has('filament-dashboards.widget-dates.7'))->toBeFalse();
});

it('needs both dates for a custom range', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Bar, 'config' => ['viewer_dates' => true, 'source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '30d']]);

    Livewire::test(BuilderChartWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7])
        ->set('deferredFilters.preset', 'custom')
        ->call('applyFilters')
        ->assertHasErrors(['deferredFilters.startDate', 'deferredFilters.endDate']);

    expect(session()->has('filament-dashboards.widget-dates.7'))->toBeFalse();
});

it('lists a table’s rows within the viewer’s dates and shows the range', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Table, 'config' => ['viewer_dates' => true, 'source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at']]);

    Livewire::test(BuilderTableWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7, 'pageFilters' => ['preset' => 30]])
        ->assertSee('Ten days ago')
        ->callAction(TestAction::make('widgetDates')->table(), data: ['preset' => 'next_30d'])
        ->assertSee('In twenty days')
        ->assertDontSee('Ten days ago')
        ->assertSee('04/11/2026 – 03/12/2026');
});

it('offers the dashboard dates only to a widget saved with its own dates on a dashboard with a date bar', function (): void {
    $own = builderWidget($this->owner, ['type' => BuilderWidgetType::Table, 'config' => ['viewer_dates' => true, 'source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at', 'period' => 'month']]);
    $following = builderWidget($this->owner, ['type' => BuilderWidgetType::Table, 'config' => ['viewer_dates' => true, 'source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at']]);
    $options = fn (int $widget, bool $bar): array => (fn (): array => $this->widgetDatesOptions())
        ->call(Livewire::test(BuilderTableWidget::class, ['builderWidgetId' => $widget, 'dashboardItemId' => 7, 'offersDashboardDates' => $bar])->instance());

    expect(array_slice($options($own->id, true), 0, 2, true))->toBe(['default' => 'Default', 'dashboard' => 'Dashboard dates'])
        ->and($options($own->id, false))->not->toHaveKey('dashboard')
        ->and(array_slice($options($following->id, true), 0, 2, true))->toBe(['default' => 'Dashboard dates', 1 => 'Today']);
});

it('ignores a stored pick the widget does not offer', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Table, 'config' => ['viewer_dates' => true, 'source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-10-20', 'period_end' => '2026-10-30']]);
    session()->put('filament-dashboards.widget-dates.7', ['preset' => 'dashboard']);

    Livewire::test(BuilderTableWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7, 'pageFilters' => ['preset' => 1]])
        ->assertSee('Ten days ago')
        ->assertDontSee('Today');
});

it('shows the dates action only on a widget with dates placed on a dashboard', function (): void {
    $dated = builderWidget($this->owner, ['config' => ['viewer_dates' => true, 'show_title' => true, 'cards' => [['source' => 'orders', 'metric' => 'count', 'dates_field' => 'ordered_at']]]]);
    $undated = builderWidget($this->owner, ['config' => ['viewer_dates' => true, 'show_title' => true, 'cards' => [['source' => 'orders', 'metric' => 'count']]]]);

    Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $dated->id, 'dashboardItemId' => 7])->assertActionVisible('widgetDates');
    Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $dated->id])->assertActionHidden('widgetDates');
    Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $undated->id, 'dashboardItemId' => 7])->assertActionHidden('widgetDates');
});

it('keeps the dates control off until the widget allows it, ignoring an earlier pick', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at']]);
    session()->put('filament-dashboards.widget-dates.7', ['preset' => 'next_30d']);

    Livewire::test(BuilderTableWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7, 'pageFilters' => ['preset' => 30]])
        ->assertActionHidden(TestAction::make('widgetDates')->table())
        ->assertSee('Ten days ago');
});

it('shows the title of a stats widget whose dates viewers may change', function (): void {
    $widget = builderWidget($this->owner, ['name' => 'Upcoming orders', 'config' => ['viewer_dates' => true, 'cards' => [['source' => 'orders', 'metric' => 'count', 'dates_field' => 'ordered_at']]]]);

    Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7])
        ->assertSee('Upcoming orders')
        ->assertActionVisible('widgetDates');
});

it('saves whether viewers may change the dates with the widget', function (): void {
    $dashboard = dashboard($this->owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('buildWidget', data: ['name' => 'Upcoming', 'source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at', 'viewer_dates' => true], arguments: ['type' => 'table'])
        ->assertHasNoActionErrors();

    expect(BuilderWidget::query()->sole()->config)->toMatchArray(['viewer_dates' => true]);
});

it('hides a chart’s dates control until the widget allows it', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Bar, 'config' => ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '30d']]);

    expect(Livewire::test(BuilderChartWidget::class, ['builderWidgetId' => $widget->id, 'dashboardItemId' => 7])->instance()->getFiltersTriggerAction()->getExtraAttributes())
        ->toBe(['style' => 'display: none']);
});
