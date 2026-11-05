<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\CentsOrdersSource;
use Waguilar\FilamentDashboards\Widgets\BuilderFiguresWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    FilamentDashboardsPlugin::get()->dataSources([CentsOrdersSource::class]);
    $this->actingAs(user());

    DemoOrderFactory::new()->create(['channel' => 'web', 'total' => 8400, 'paid' => true]);
    DemoOrderFactory::new()->create(['channel' => 'store', 'total' => 2800, 'paid' => false]);
});

/**
 * @param  array<string, mixed>  $config
 */
function figures(array $config): mixed
{
    return Livewire::test(BuilderFiguresWidget::class, ['previewType' => 'figures', 'previewConfig' => $config]);
}

it('shows figure and calculation columns with a share row and further rows', function (): void {
    figures([
        'columns' => [
            ['key' => 'sales', 'label' => 'Sales', 'source' => 'cents-orders', 'metric' => 'sum', 'field' => 'total', 'share_of' => 'sales'],
            ['key' => 'paid', 'label' => 'Paid', 'source' => 'cents-orders', 'metric' => 'sum', 'field' => 'total', 'share_of' => 'sales', 'blank_rows' => ['net'], 'filters' => [['rules' => [['field' => 'paid', 'operator' => 'is', 'choice' => '1']]]]],
            ['key' => 'owed', 'label' => 'Owed', 'kind' => 'calculation', 'left' => 'sales', 'operator' => 'subtract', 'right' => 'paid'],
            ['key' => 'orders', 'label' => 'Orders', 'source' => 'cents-orders', 'metric' => 'count'],
        ],
        'base_label' => 'Total sales',
        'rows' => [['key' => 'net', 'label' => 'Net of VAT', 'operator' => 'divide', 'number' => '1.12']],
    ])
        ->assertSeeInOrder(['100.0%', 'Sales', '75.0%', 'Paid', 'Owed', 'Orders'])
        ->assertSeeInOrder(['Total sales', 'Q1,120.00', 'Q840.00', 'Q280.00', '2'])
        ->assertSeeInOrder(['Net of VAT', 'Q1,000.00', 'Q250.00', '1.79']);
});

it('leaves a cell empty when a calculation divides by zero', function (): void {
    figures([
        'columns' => [
            ['key' => 'orders', 'label' => 'Orders', 'source' => 'cents-orders', 'metric' => 'count'],
            ['key' => 'per', 'label' => 'Per nothing', 'kind' => 'calculation', 'left' => 'orders', 'operator' => 'divide', 'right' => BuilderFiguresWidget::NUMBER, 'right_number' => '0'],
        ],
        'base_label' => 'Total',
    ])
        ->assertSee('Per nothing')
        ->assertSeeInOrder(['Total', '2']);
});

it('builds a figures table from the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->mountAction('buildWidget', ['type' => 'figures'])
        ->fillForm([
            'name' => 'Sales by date',
            'columns' => [
                ['key' => 'sales', 'label' => 'Sales', 'kind' => 'figure', 'source' => 'cents-orders', 'metric' => 'sum', 'field' => 'total'],
                ['key' => 'half', 'label' => 'Half', 'kind' => 'calculation', 'left' => 'sales', 'operator' => 'divide', 'right' => BuilderFiguresWidget::NUMBER, 'right_number' => '2'],
            ],
            'base_label' => 'Total',
            'rows' => [['key' => 'net', 'label' => 'Net', 'operator' => 'divide', 'number' => '1.12']],
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $widget = BuilderWidget::query()->sole();

    expect($widget->type)->toBe(BuilderWidgetType::Figures)
        ->and($widget->config['columns'][1]['right_number'])->toBe(2)
        ->and($widget->sourceKeys())->toBe(['cents-orders'])
        ->and($dashboard->items()->sole()->only(['w', 'h']))->toBe(['w' => 12, 'h' => 4]);
});

it('offers a calculation only the columns before it', function (): void {
    $owner = user();
    $this->actingAs($owner);

    $page = Livewire::test(ViewDashboard::class, ['record' => dashboard($owner)->slug])
        ->mountAction('buildWidget', ['type' => 'figures'])
        ->fillForm(['columns' => [
            ['key' => 'a', 'label' => 'First', 'kind' => 'figure'],
            ['key' => 'b', 'label' => 'Second', 'kind' => 'calculation'],
            ['key' => 'c', 'label' => 'Third', 'kind' => 'figure'],
        ]])
        ->instance();

    $columns = $page->getSchema($page->getMountedActionSchemaName())?->getComponent(fn (mixed $component): bool => $component instanceof Repeater && $component->getName() === 'columns');
    $calculation = collect($columns?->getItems() ?? [])->first(fn (Schema $item): bool => $item->getRawState()['key'] === 'b');
    $left = $calculation?->getComponent(fn (mixed $component): bool => $component instanceof Select && $component->getName() === 'left', withHidden: true);

    expect(array_keys($left?->getOptions() ?? []))->toBe(['a', BuilderFiguresWidget::NUMBER]);
});

it('follows the dashboard dates through each figure column', function (): void {
    $widget = builderWidget(user(), ['type' => BuilderWidgetType::Figures, 'config' => ['columns' => [
        ['key' => 'a', 'source' => 'cents-orders', 'metric' => 'count'],
        ['key' => 'b', 'source' => 'cents-orders', 'metric' => 'count', 'dates_field' => 'ordered_at'],
    ]]]);

    expect($widget->followsDashboardDates())->toBeTrue();
});
