<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Exceptions\MethodNotFoundException;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\ResolveWidgetSkeleton;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Exports\CsvExporter;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Renderers\FilamentTableRenderer;
use Waguilar\FilamentDashboards\Renderers\TableResult;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\Renderers\ListRenderer;
use Waguilar\FilamentDashboards\Tests\Fixtures\Renderers\ListWidget;
use Waguilar\FilamentDashboards\Tests\Fixtures\Renderers\SchemaWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderSummaryWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00'));
    FilamentDashboardsPlugin::get()
        ->renderer(BuilderWidgetType::Summary, ListRenderer::class)
        ->renderer(BuilderWidgetType::Table, new ListRenderer)
        ->formatDatesUsing(fn (CarbonInterface $date, string $context): string => $date->format('d/m/Y'));
    $this->owner = user();
    $this->actingAs($this->owner);

    DemoOrderFactory::new()->create(['customer' => 'Ana', 'channel' => 'web', 'status' => 'paid', 'total' => 300, 'ordered_at' => now()]);
    DemoOrderFactory::new()->create(['customer' => 'Eva', 'channel' => 'web', 'status' => 'pending', 'total' => 450, 'ordered_at' => now()->subDays(10)]);
    DemoOrderFactory::new()->create(['customer' => 'Luis', 'channel' => 'store', 'status' => 'paid', 'total' => 250.5, 'ordered_at' => now()->subDay()]);
});

/**
 * @param  array<string, mixed>  $config
 * @param  array<string, mixed>  $props
 */
function listed(array $config = [], array $props = [], array $widget = []): mixed
{
    $saved = builderWidget(test()->owner, ['type' => BuilderWidgetType::Summary, 'name' => 'Sales by channel', 'config' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'sum', 'field' => 'total', 'percent' => true]], 'total_row' => true, ...$config], ...$widget]);

    return Livewire::test(ListWidget::class, ['builderWidgetId' => $saved->id, ...$props]);
}

function resultOf(object $component): TableResult
{
    return (fn (): TableResult => $this->tableResult())->call($component->instance());
}

it('mounts the registered renderer for its types on the dashboard and in the builder preview, with its skeleton', function (): void {
    $dashboard = dashboard($this->owner);
    $summary = builderWidget($this->owner, ['type' => BuilderWidgetType::Summary, 'config' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'count']]]]);
    $dashboard->items()->create(['widget' => $summary->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    $html = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->html();

    expect(BuilderWidgetForm::component(BuilderWidgetType::Summary))->toBe(ListWidget::class)
        ->and(BuilderWidgetForm::component(BuilderWidgetType::Table))->toBe(ListWidget::class)
        ->and(app(ResolveWidgetSkeleton::class)->handle(ListWidget::class, $summary)['shape'])->toBe('card')
        ->and($html)->toContain('list-widget')
        ->toContain('data-skeleton="card"')
        ->toContain('data-adjustable-height');
});

it('keeps the built-in Filament table for types without a registered renderer', function (): void {
    FilamentDashboardsPlugin::get()->renderer(BuilderWidgetType::Table, FilamentTableRenderer::class);

    expect(BuilderWidgetForm::component(BuilderWidgetType::Table))->toBe(BuilderTableWidget::class)
        ->and(app(FilamentTableRenderer::class)->widget(BuilderWidgetType::Summary))->toBe(BuilderSummaryWidget::class)
        ->and(BuilderWidgetForm::component(BuilderWidgetType::Stats))->not->toBe(ListWidget::class);
});

it('draws the result inside the plugin card with its heading, range, actions and polling', function (): void {
    FilamentDashboardsPlugin::get()->minRefreshInterval(10);

    listed(['dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'viewer_dates' => true], ['dashboardItemId' => 4], ['refresh_interval' => 30])
        ->assertSeeHtml('class="fi-ta-header-heading">Sales by channel')
        ->assertSee('01/11/2026 – 30/11/2026')
        ->assertSeeHtml("mountAction('widgetDates')")
        ->assertSeeHtml("mountAction('export_csv')")
        ->assertSeeHtml('wire:poll.30s')
        ->assertSeeInOrder(['Channel: Web', 'Sum · Total: 750', '% of total: 74.96%', 'Channel: Store', 'Channel: Total', 'Sum · Total: 1,000.50', '% of total: 100.00%']);
});

it('hands the renderer a result with raw values, display text and drill-down arguments', function (): void {
    $result = resultOf(listed());

    expect($result->toArray())->toBe([
        'columns' => [
            ['key' => 'group', 'label' => 'Channel', 'type' => 'text', 'alignment' => 'start'],
            ['key' => 'value_0', 'label' => 'Sum · Total', 'type' => 'number', 'alignment' => 'end'],
            ['key' => 'percent_0', 'label' => '% of total', 'type' => 'percent', 'alignment' => 'end'],
        ],
        'rows' => [
            ['values' => ['group' => 'Web', 'value_0' => 750, 'percent_0' => 74.96251874062968], 'display' => ['group' => 'Web', 'value_0' => '750', 'percent_0' => '74.96%'], 'drillDown' => ['key' => 'web', 'label' => 'Web']],
            ['values' => ['group' => 'Store', 'value_0' => 250.5, 'percent_0' => 25.037481259370313], 'display' => ['group' => 'Store', 'value_0' => '250.50', 'percent_0' => '25.04%'], 'drillDown' => ['key' => 'store', 'label' => 'Store']],
        ],
        'totals' => ['values' => ['group' => 'Total', 'value_0' => 1000.5, 'percent_0' => 100.0], 'display' => ['group' => 'Total', 'value_0' => '1,000.50', 'percent_0' => '100.00%'], 'drillDown' => null],
        'needsAttention' => false,
    ])
        ->and(json_decode((string) json_encode($result->toArray()), true))->toEqual($result->toArray());
});

it('gives the renderer drill-down without extra code', function (): void {
    $component = listed()
        ->assertSeeHtml('wire:click="mountAction(\'drillDown\', JSON.parse(')
        ->mountAction(TestAction::make('drillDown')->arguments(['key' => 'web', 'label' => 'Web']))
        ->assertActionMounted('drillDown');

    $instance = $component->instance();
    $records = $instance->getSchema($instance->getMountedActionSchemaName())?->getComponent('records')?->getState() ?? [];

    expect(collect($records)->pluck('customer')->sort()->values()->all())->toBe(['Ana', 'Eva']);
});

it('gives the renderer export without extra code', function (): void {
    $component = listed();
    $response = (fn () => $this->exportWith(new CsvExporter))->call($component->instance());

    ob_start();
    $response->sendContent();

    expect((string) ob_get_clean())->toBe("\u{FEFF}Channel,\"Sum · Total\",\"% of total\"\nWeb,750,74.96251874063\nStore,250.5,25.03748125937\nTotal,1000.5,100\n");

    $component->call('mountAction', 'export_csv')->assertFileDownloaded('sales-by-channel-2026-11-20.csv');
});

it('gives the renderer the dates action, which redraws the result', function (): void {
    listed(['dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-11-15', 'period_end' => '2026-11-30', 'viewer_dates' => true], ['dashboardItemId' => 4])
        ->assertSee('Sum · Total: 300')
        ->call('mountAction', 'widgetDates')
        ->set('mountedActions.0.data', ['preset' => 'custom', 'startDate' => '2026-11-01', 'endDate' => '2026-11-30'])
        ->call('callMountedAction')
        ->assertHasNoErrors()
        ->assertSee('Sum · Total: 750')
        ->assertSee('01/11/2026 – 30/11/2026');
});

it('shows the empty state, and why when a saved filter no longer matches', function (): void {
    listed(['filters' => [['rules' => [['field' => 'status', 'operator' => 'is', 'choice' => 'refunded']]]], 'total_row' => false])
        ->assertSeeHtml('fi-ta-empty-state-heading')
        ->assertDontSeeHtml('list-renderer')
        ->assertDontSee(__('filament-dashboards::builder.needs_attention'));

    $stale = listed(['filters' => [['rules' => [['field' => 'renamed', 'operator' => 'is', 'value' => 'x']]]]])
        ->assertSeeHtml('fi-ta-empty-state-heading')
        ->assertSee(__('filament-dashboards::builder.needs_attention'));

    expect(resultOf($stale)->needsAttention)->toBeTrue();
});

it('hands a table widget its rows with raw and display values and no drill-down', function (): void {
    $result = resultOf(Livewire::test(ListWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'orders', 'columns' => ['customer', 'total', 'ordered_at'], 'sort_field' => 'total', 'sort_direction' => 'desc', 'limit' => 1]]));

    expect($result->toArray())->toBe([
        'columns' => [
            ['key' => 'customer', 'label' => 'Customer', 'type' => 'text', 'alignment' => 'start'],
            ['key' => 'total', 'label' => 'Total', 'type' => 'number', 'alignment' => 'start'],
            ['key' => 'ordered_at', 'label' => 'Ordered at', 'type' => 'date', 'alignment' => 'start'],
        ],
        'rows' => [
            ['values' => ['customer' => 'Eva', 'total' => 450, 'ordered_at' => '2026-11-10 12:00:00'], 'display' => ['customer' => 'Eva', 'total' => '450', 'ordered_at' => '2026-11-10 12:00:00'], 'drillDown' => null],
        ],
        'totals' => null,
        'needsAttention' => false,
    ]);
});

it('only lets table and summary widgets take a renderer, and only a renderer class', function (): void {
    expect(fn () => FilamentDashboardsPlugin::get()->renderer(BuilderWidgetType::Line, ListRenderer::class))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FilamentDashboardsPlugin::get()->renderer(BuilderWidgetType::Table, ListWidget::class)->getRenderer(BuilderWidgetType::Table))->toThrow(InvalidArgumentException::class)
        ->and(FilamentDashboardsPlugin::get()->getRenderer(BuilderWidgetType::Stats))->toBeNull();
});

it('keeps the renderer helpers off the browser', function (string $method): void {
    listed()->call($method);
})->with(['tableResult', 'builderHeaderActions', 'body'])->throws(MethodNotFoundException::class);

it('lets a renderer draw its body with a Filament schema', function (): void {
    Livewire::test(SchemaWidget::class, ['previewType' => 'summary', 'previewConfig' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'count']]]])
        ->assertSee('Web, Store');
});
