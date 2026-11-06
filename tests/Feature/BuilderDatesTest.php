<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00:00'));
    $this->actingAs(user());

    DemoOrderFactory::new()->create(['channel' => 'web', 'total' => 100, 'ordered_at' => now(), 'customer' => 'Today']);
    DemoOrderFactory::new()->create(['channel' => 'web', 'total' => 50, 'ordered_at' => now()->subDays(10), 'customer' => 'Ten days']);
    DemoOrderFactory::new()->create(['channel' => 'store', 'total' => 25, 'ordered_at' => now()->subDays(40), 'customer' => 'Forty days']);
});

/**
 * @return array<string, mixed>
 */
function datedChart(array $config, string $type, ?array $filters): array
{
    $chart = Livewire::test(BuilderChartWidget::class, ['previewType' => $type, 'previewConfig' => $config, 'pageFilters' => $filters])->instance();

    return (fn (): array => $this->getData())->call($chart);
}

it('filters stats cards by the dashboard dates only when a date field is set', function (): void {
    $cards = ['cards' => [
        ['source' => 'orders', 'metric' => 'sum', 'field' => 'total', 'label' => 'Dated', 'dates_field' => 'ordered_at'],
        ['source' => 'orders', 'metric' => 'count', 'label' => 'Undated'],
    ]];

    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => $cards, 'pageFilters' => ['preset' => 15]])
        ->assertSeeInOrder(['Dated', '150', 'Undated', '3']);
});

it('filters breakdowns by the dashboard dates only when a date field is set', function (): void {
    $config = ['source' => 'orders', 'group_field' => 'channel', 'metric' => 'count'];

    expect(datedChart([...$config, 'dates_field' => 'ordered_at'], 'pie', ['preset' => 1])['datasets'][0]['data'])->toBe([1])
        ->and(datedChart($config, 'pie', ['preset' => 1])['datasets'][0]['data'])->toBe([2, 1]);
});

it('filters scatter points by the dashboard dates when a date field is set', function (): void {
    $config = ['source' => 'orders', 'x_field' => 'total', 'y_field' => 'items_count', 'dates_field' => 'ordered_at'];

    expect(datedChart($config, 'scatter', ['preset' => 15])['datasets'][0]['data'])->toHaveCount(2);
});

it('filters table rows by the dashboard dates only when a date field is set', function (): void {
    $config = ['source' => 'orders', 'columns' => ['customer'], 'sort_field' => 'total'];

    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => [...$config, 'dates_field' => 'ordered_at'], 'pageFilters' => ['preset' => 1]])
        ->assertSee('Today')
        ->assertDontSee('Ten days');

    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => $config, 'pageFilters' => ['preset' => 1]])
        ->assertSee('Ten days')
        ->assertSee('Forty days');
});

it('spans a trend over the dashboard dates when its period follows them', function (): void {
    $config = ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'sum', 'field' => 'total'];

    $dashboard = datedChart([...$config, 'period' => 'dashboard'], 'line', ['preset' => 15]);
    $custom = datedChart([...$config, 'period' => 'dashboard'], 'line', ['preset' => 'custom', 'startDate' => '2026-09-01', 'endDate' => '2026-11-04']);
    $own = datedChart([...$config, 'period' => '12m'], 'line', ['preset' => 15]);

    expect($dashboard['labels'])->toHaveCount(15)
        ->and(array_sum($dashboard['datasets'][0]['data']))->toBe(150)
        ->and(array_sum($custom['datasets'][0]['data']))->toBe(175)
        ->and($custom['labels'])->toHaveCount(10)
        ->and($own['labels'])->toHaveCount(12);
});

it('shows the date bar for a builder widget that follows the dates, and not for one that does not', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $following = builderWidget($owner, ['type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer'], 'dates_field' => 'ordered_at']]);
    $plain = builderWidget($owner, ['type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer']]]);
    $item = $dashboard->items()->create(['widget' => $plain->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertDontSeeHtml('filtersForm.preset');

    $item->update(['widget' => $following->dashboardKey()]);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertSeeHtml('filtersForm.preset');
});
