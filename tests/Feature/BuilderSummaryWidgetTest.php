<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\CentsOrdersSource;
use Waguilar\FilamentDashboards\Widgets\BuilderSummaryWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    FilamentDashboardsPlugin::get()->dataSources([CentsOrdersSource::class]);
    $this->actingAs(user());

    DemoOrderFactory::new()->create(['channel' => 'web', 'total' => 300, 'items_count' => 1]);
    DemoOrderFactory::new()->create(['channel' => 'web', 'total' => 450, 'items_count' => 3]);
    DemoOrderFactory::new()->create(['channel' => 'store', 'total' => 250, 'items_count' => 2]);
});

/**
 * @param  array<string, mixed>  $config
 */
function summary(array $config): mixed
{
    return Livewire::test(BuilderSummaryWidget::class, ['previewType' => 'summary', 'previewConfig' => ['source' => 'cents-orders', 'group_field' => 'channel', ...$config]]);
}

it('shows one row per group with each metric, its label and the field format', function (): void {
    summary(['metrics' => [
        ['metric' => 'sum', 'field' => 'total', 'label' => 'Sales'],
        ['metric' => 'average', 'field' => 'items_count', 'percent' => true],
    ]])
        ->assertSeeInOrder(['Channel', 'Sales', 'Average · Items'])
        ->assertSeeInOrder(['Web', 'Q75.00', '2', 'Store', 'Q25.00', '2'])
        ->assertDontSee('% of total')
        ->assertDontSee('Total');
});

it('adds a share of the total next to a count or sum, and a total row', function (): void {
    summary([
        'metrics' => [['metric' => 'sum', 'field' => 'total', 'percent' => true], ['metric' => 'count', 'percent' => true]],
        'total_row' => true,
    ])
        ->assertSeeInOrder(['Sum · Total', '% of total', 'Count', '% of total'])
        ->assertSeeInOrder(['Web', 'Q75.00', '75.0%', '2', '66.7%'])
        ->assertSeeInOrder(['Store', 'Q25.00', '25.0%', '1', '33.3%'])
        ->assertSeeInOrder(['Total', 'Q100.00', '100.0%', '3', '100.0%']);
});

it('builds a summary table from the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('buildWidget', data: [
            'name' => 'Sales by channel',
            'source' => 'cents-orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'sum', 'field' => 'total', 'percent' => true]], 'total_row' => true,
        ], arguments: ['type' => 'summary'])
        ->assertHasNoActionErrors();

    expect(BuilderWidget::query()->sole()->type)->toBe(BuilderWidgetType::Summary)
        ->and($dashboard->items()->sole()->only(['w', 'h']))->toBe(['w' => 6, 'h' => 6]);
});
