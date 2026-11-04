<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\BuildFilters;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Pages\ListBuilderWidgets;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Widgets\BuilderChartWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

/**
 * @return array<string, mixed>
 */
function chartData(BuilderChartWidget $chart): array
{
    return (fn (): array => $this->getData())->call($chart);
}

function demoOrders(): void
{
    DemoOrderFactory::new()->create(['channel' => 'web', 'status' => 'paid', 'paid' => true, 'total' => 100, 'ordered_at' => now()->subDays(2), 'customer' => 'Ana']);
    DemoOrderFactory::new()->create(['channel' => 'web', 'status' => 'pending', 'paid' => false, 'total' => 50, 'ordered_at' => now()->subDays(1), 'customer' => 'Luis']);
    DemoOrderFactory::new()->create(['channel' => 'store', 'status' => 'paid', 'paid' => true, 'total' => 25, 'ordered_at' => now(), 'customer' => 'Eva']);
}

it('builds a widget and places it on the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('buildWidget', data: [
            'name' => 'Revenue',
            'refresh_interval' => 30,
            'config' => ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'sum', 'field' => 'total', 'period' => '30d'],
        ], arguments: ['type' => 'line'])
        ->assertHasNoActionErrors();

    $widget = BuilderWidget::query()->sole();

    expect($widget->isOwnedBy($owner))->toBeTrue()
        ->and($widget->type)->toBe(BuilderWidgetType::Line)
        ->and($widget->refresh_interval)->toBe(30)
        ->and($widget->config['field'])->toBe('total')
        ->and($dashboard->items()->sole()->only(['widget', 'w']))->toBe(['widget' => "custom:{$widget->id}", 'w' => 6]);
});

it('opens the builder when a new widget type is picked', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('addWidget', data: ['widget' => 'new:pie'])
        ->assertActionMounted('buildWidget');
});

it('adds a saved widget the user can see', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $widget = builderWidget(user());
    share($widget, $owner, ShareLevel::Read);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('addWidget', data: ['widget' => $widget->dashboardKey()])
        ->assertHasNoActionErrors();

    expect($dashboard->items()->sole()->widget)->toBe($widget->dashboardKey());
});

it('edits a widget only for those allowed to edit it', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $mine = builderWidget($owner);
    $readOnly = builderWidget(user());
    share($readOnly, $owner, ShareLevel::Read);
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->callAction('editWidget', data: ['name' => 'Renamed', 'config' => ['cards' => [['source' => 'orders', 'metric' => 'count']]]], arguments: ['widget' => $mine->id])
        ->assertHasNoActionErrors();

    expect($mine->refresh()->name)->toBe('Renamed');

    $page->mountAction('editWidget', ['widget' => $readOnly->id])->assertForbidden();
});

it('shows stats cards computed for the viewer', function (): void {
    demoOrders();
    $this->actingAs(user());

    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => [
        ['source' => 'orders', 'metric' => 'count', 'label' => 'All orders'],
        ['source' => 'orders', 'metric' => 'sum', 'field' => 'total', 'label' => 'Web revenue', 'filters' => [['rules' => [['field' => 'channel', 'operator' => 'is', 'choice' => 'web']]]]],
        ['source' => 'missing', 'metric' => 'count'],
        ['source' => 'orders', 'metric' => 'sum', 'field' => 'customer'],
    ]]])
        ->assertSee('All orders')
        ->assertSee('3')
        ->assertSee('Web revenue')
        ->assertSee('150');
});

it('charts a breakdown with one colour per slice', function (): void {
    demoOrders();
    $this->actingAs(user());

    $data = chartData(Livewire::test(BuilderChartWidget::class, ['previewType' => 'doughnut', 'previewConfig' => ['source' => 'orders', 'group_field' => 'channel', 'metric' => 'count']])->instance());

    expect($data['labels'])->toBe(['Web', 'Store'])
        ->and($data['datasets'][0]['backgroundColor'])->toHaveCount(2);
});

it('charts a trend and a scatter', function (string $type, array $config, string $key): void {
    demoOrders();
    $this->actingAs(user());

    $data = chartData(Livewire::test(BuilderChartWidget::class, ['previewType' => $type, 'previewConfig' => $config])->instance());

    expect($data['datasets'][0]['data'])->not->toBeEmpty()
        ->and(array_key_exists($key, $data))->toBeTrue();
})->with([
    'trend by day' => ['bar', ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '7d'], 'labels'],
    'trend by week' => ['line', ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => '90d'], 'labels'],
    'trend by month' => ['line', ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'count', 'period' => 'ytd', 'grouping' => 'day'], 'labels'],
    'scatter' => ['scatter', ['source' => 'orders', 'x_field' => 'total', 'y_field' => 'items_count'], 'datasets'],
]);

it('charts nothing for a source or field that does not exist', function (array $config): void {
    $this->actingAs(user());

    $data = chartData(Livewire::test(BuilderChartWidget::class, ['previewType' => 'line', 'previewConfig' => $config])->instance());

    expect($data['datasets'])->toBe([]);
})->with([
    'no source' => [['source' => 'missing']],
    'bad date field' => [['source' => 'orders', 'date_field' => 'customer']],
]);

it('lists rows in a table', function (): void {
    demoOrders();
    $this->actingAs(user());

    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'orders', 'columns' => ['customer', 'status'], 'sort_field' => 'total', 'sort_direction' => 'desc', 'limit' => 2]])
        ->assertSeeInOrder(['Ana', 'Paid', 'Luis', 'Pending'])
        ->assertDontSee('Eva');
});

it('shows a saved widget only to people the widget itself is shared with', function (): void {
    $owner = user();
    $widget = builderWidget($owner, ['name' => 'Team numbers']);
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => $widget->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]);
    $teammate = user();
    share($dashboard, $teammate, ShareLevel::Read);

    $this->actingAs($teammate);
    Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $widget->id])->assertNotFound();
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertSee('No widgets yet');

    share($widget, $teammate, ShareLevel::Read);

    Livewire::test(BuilderStatsWidget::class, ['builderWidgetId' => $widget->id])->assertSee('Team numbers');
    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->assertDontSee('No widgets yet');
});

it('polls a saved widget at its refresh interval', function (): void {
    $owner = user();
    $widget = builderWidget($owner, ['refresh_interval' => 30, 'type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer']]]);
    $this->actingAs($owner);

    Livewire::test(BuilderTableWidget::class, ['builderWidgetId' => $widget->id])->assertSeeHtml('wire:poll.30s');
});

it('turns form filter groups into engine filters', function (): void {
    expect(app(BuildFilters::class)->handle([
        ['rules' => [['field' => 'status', 'operator' => 'is', 'choice' => 'paid'], ['field' => 'total', 'operator' => 'greater_than', 'value' => '10'], 'junk']],
        ['rules' => []],
        'junk',
    ]))->toBe([[
        ['field' => 'status', 'operator' => 'is', 'value' => 'paid'],
        ['field' => 'total', 'operator' => 'greater_than', 'value' => '10'],
    ]]);
});

it('lists saved widgets by kind and clones one', function (): void {
    $me = user();
    $mine = builderWidget($me, ['name' => 'Mine']);
    $system = builderWidget(attributes: ['name' => 'System', 'system_key' => 'system']);
    $hidden = builderWidget(user(), ['name' => 'Hidden']);
    $this->actingAs($me);

    Livewire::test(ListBuilderWidgets::class)
        ->assertCanSeeTableRecords([$mine, $system])
        ->assertCanNotSeeTableRecords([$hidden])
        ->assertTableActionHidden('edit', $system)
        ->callTableAction('clone', $system, data: ['name' => 'My copy'])
        ->assertHasNoTableActionErrors();

    expect(BuilderWidget::query()->where('name', 'My copy')->sole()->isOwnedBy($me))->toBeTrue();
});

it('saves filters built in the form', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->mountAction('buildWidget', ['type' => 'stats'])
        ->fillForm([
            'name' => 'Paid web orders',
            'config' => ['cards' => [[
                'source' => 'orders',
                'metric' => 'count',
                'label' => 'Paid web',
                'filters' => [['rules' => [
                    ['field' => 'channel', 'operator' => 'is', 'choice' => 'web'],
                    ['field' => 'paid', 'operator' => 'is', 'choice' => '1'],
                    ['field' => 'ordered_at', 'operator' => 'after', 'value' => '2026-01-01'],
                ]]],
            ]]],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $rules = BuilderWidget::query()->sole()->config['cards'][0]['filters'][0]['rules'];

    expect(array_column($rules, 'field'))->toBe(['channel', 'paid', 'ordered_at']);
});
