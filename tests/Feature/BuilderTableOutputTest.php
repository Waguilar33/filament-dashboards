<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\ResolveWidgetSkeleton;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Exports\CsvExporter;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\CentsOrdersSource;
use Waguilar\FilamentDashboards\Widgets\BuilderSummaryWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;
use Workbench\App\DataSources\OrdersSource;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
    $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00:00'));
    FilamentDashboardsPlugin::get()
        ->dataSources([OrdersSource::class, CentsOrdersSource::class])
        ->formatDatesUsing(fn (CarbonInterface $date, string $context): string => $date->format('d/m/Y'));
    $this->owner = user();
    $this->actingAs($this->owner);

    DemoOrderFactory::new()->create(['customer' => 'Ana', 'channel' => 'web', 'status' => 'paid', 'total' => 300, 'items_count' => 1, 'ordered_at' => now()]);
    DemoOrderFactory::new()->create(['customer' => 'Luis', 'channel' => 'store', 'status' => 'paid', 'total' => 250.5, 'items_count' => 2, 'ordered_at' => now()->subDay()]);
    DemoOrderFactory::new()->create(['customer' => 'Eva', 'channel' => 'web', 'status' => 'pending', 'total' => 450, 'items_count' => 3, 'ordered_at' => now()->subDays(2)]);
});

/**
 * @return list<list<array{text: string, class: string, click: string}>>
 */
function tableCells(string $html): array
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
    $xpath = new DOMXPath($document);
    $rows = [];

    foreach ($xpath->query('//tr[contains(concat(" ", @class, " "), " fi-ta-row ")]') ?: [] as $row) {
        $cells = [];

        foreach ($xpath->query('./td', $row) ?: [] as $cell) {
            $text = $xpath->query('.//*[contains(concat(" ", @class, " "), " fi-ta-text-item ")]', $cell)?->item(0);

            $cells[] = [
                'text' => trim(preg_replace('/\s+/', ' ', $cell->textContent) ?? ''),
                'class' => trim($cell->getAttribute('class').' '.($text instanceof DOMElement ? $text->getAttribute('class') : '')),
                'click' => $cell->getAttribute('wire:click'),
            ];
        }

        $rows[] = $cells;
    }

    return $rows;
}

/**
 * @param  array<string, mixed>  $config
 */
function summaryHtml(array $config, array $widget = [], array $props = []): string
{
    $saved = builderWidget(test()->owner, ['type' => BuilderWidgetType::Summary, 'name' => 'Sales by channel', 'config' => ['source' => 'orders', 'group_field' => 'channel', ...$config], ...$widget]);

    return Livewire::test(BuilderSummaryWidget::class, ['builderWidgetId' => $saved->id, ...$props])->html();
}

function csvOf(object $component): string
{
    $response = (fn () => $this->exportWith(new CsvExporter))->call($component->instance());

    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('draws a summary with its heading, columns in order, formats, end-aligned metrics and a bold total row', function (): void {
    $html = summaryHtml(['metrics' => [['metric' => 'sum', 'field' => 'total', 'percent' => true], ['metric' => 'count', 'label' => 'Orders']], 'total_row' => true]);
    $rows = tableCells($html);

    expect($html)->toContain('Sales by channel')
        ->and(array_map(fn (array $row): array => array_column($row, 'text'), $rows))->toBe([
            ['Web', '750', '74.96%', '2'],
            ['Store', '250.50', '25.04%', '1'],
            ['Total', '1,000.50', '100.00%', '3'],
        ])
        ->and($rows[0][0]['class'])->not->toContain('fi-align-end')
        ->and(collect(array_slice($rows[0], 1))->every(fn (array $cell): bool => str_contains($cell['class'], 'fi-align-end')))->toBeTrue()
        ->and(collect($rows[2])->every(fn (array $cell): bool => str_contains($cell['class'], 'fi-font-bold')))->toBeTrue()
        ->and(collect([...$rows[0], ...$rows[1]])->contains(fn (array $cell): bool => str_contains($cell['class'], 'fi-font-bold')))->toBeFalse();

    Livewire::test(BuilderSummaryWidget::class, ['previewType' => 'summary', 'previewConfig' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'sum', 'field' => 'total', 'percent' => true], ['metric' => 'count', 'label' => 'Orders']]]])
        ->assertSeeInOrder(['Channel', 'Sum · Total', '% of total', 'Orders']);
});

it('formats summary values with the field format', function (): void {
    expect(array_column(tableCells(summaryHtml(['source' => 'cents-orders', 'metrics' => [['metric' => 'average', 'field' => 'total']]]))[0], 'text'))
        ->toBe(['Web', 'Q37.50']);
});

it('opens the drill-down from a group cell but not from the total row', function (): void {
    $rows = tableCells(summaryHtml(['metrics' => [['metric' => 'count']], 'total_row' => true]));

    expect($rows[0][0]['click'])->toBe("mountAction('drillDown', JSON.parse('{\\u0022key\\u0022:\\u0022web\\u0022,\\u0022label\\u0022:\\u0022Web\\u0022}'))")
        ->and($rows[0][0]['class'])->toContain('fi-dashboards-drill-down')
        ->and($rows[0][1]['click'])->toBe('')
        ->and($rows[2][0]['click'])->toBe('')
        ->and($rows[2][0]['class'])->not->toContain('fi-dashboards-drill-down');
});

it('offers no drill-down when the source declares no fields for it', function (): void {
    $rows = tableCells(summaryHtml(['source' => 'cents-orders', 'metrics' => [['metric' => 'count']]]));

    expect(array_column(array_column($rows, 0), 'click'))->toBe(['', '']);
});

it('shows the widget range and the dates action only where the viewer may change dates', function (): void {
    $config = ['metrics' => [['metric' => 'count']], 'dates_field' => 'ordered_at', 'period' => 'custom', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'viewer_dates' => true];

    expect(summaryHtml($config, props: ['dashboardItemId' => 4]))->toContain('01/11/2026 – 30/11/2026')
        ->toContain("mountAction('widgetDates'")
        ->and(summaryHtml($config))->not->toContain("mountAction('widgetDates'")
        ->and(summaryHtml([...$config, 'viewer_dates' => false], props: ['dashboardItemId' => 4]))->not->toContain("mountAction('widgetDates'");
});

it('polls at the widget refresh interval, never faster than the panel minimum', function (): void {
    FilamentDashboardsPlugin::get()->minRefreshInterval(10);

    expect(summaryHtml(['metrics' => [['metric' => 'count']]], ['refresh_interval' => 30]))->toContain('wire:poll.30s')
        ->and(summaryHtml(['metrics' => [['metric' => 'count']]], ['refresh_interval' => 2]))->toContain('wire:poll.10s')
        ->and(summaryHtml(['metrics' => [['metric' => 'count']]]))->not->toContain('wire:poll');
});

it('shows the empty state, and the needs-attention text when a saved filter no longer matches', function (): void {
    $empty = summaryHtml(['metrics' => [['metric' => 'count']], 'filters' => [['rules' => [['field' => 'status', 'operator' => 'is', 'choice' => 'refunded']]]]]);
    $stale = summaryHtml(['metrics' => [['metric' => 'count']], 'filters' => [['rules' => [['field' => 'renamed', 'operator' => 'is', 'value' => 'x']]]]]);

    expect($empty)->toContain('fi-ta-empty-state-heading')
        ->not->toContain(__('filament-dashboards::builder.needs_attention'))
        ->and($stale)->toContain('fi-ta-empty-state-heading')
        ->toContain(__('filament-dashboards::builder.needs_attention'));
});

it('draws a table widget with its columns, raw text and formatted numbers', function (): void {
    $widget = builderWidget($this->owner, ['type' => BuilderWidgetType::Table, 'name' => 'Latest orders', 'config' => ['source' => 'cents-orders', 'columns' => ['customer', 'channel', 'total', 'ordered_at'], 'sort_field' => 'total', 'sort_direction' => 'desc']]);
    $component = Livewire::test(BuilderTableWidget::class, ['builderWidgetId' => $widget->id])
        ->assertSeeInOrder(['Latest orders', 'Customer', 'Channel', 'Total', 'Ordered']);

    expect(array_map(fn (array $row): array => array_column($row, 'text'), tableCells($component->html())))->toBe([
        ['Eva', 'Web', 'Q45.00', '2026-11-18 12:00:00'],
        ['Ana', 'Web', 'Q30.00', '2026-11-20 12:00:00'],
        ['Luis', 'Store', 'Q25.05', '2026-11-19 12:00:00'],
    ]);
});

it('exports a summary with its raw values, shares and total row', function (): void {
    $component = Livewire::test(BuilderSummaryWidget::class, ['previewType' => 'summary', 'previewConfig' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'sum', 'field' => 'total', 'percent' => true], ['metric' => 'count', 'label' => 'Orders']], 'total_row' => true]]);

    expect(csvOf($component))->toBe("\u{FEFF}Channel,\"Sum · Total\",\"% of total\",Orders\nWeb,750,74.96251874063,2\nStore,250.5,25.03748125937,1\nTotal,1000.5,100,3\n");
});

it('exports a table with its raw values', function (): void {
    $component = Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'cents-orders', 'columns' => ['customer', 'total'], 'sort_field' => 'total', 'sort_direction' => 'desc']]);

    expect(csvOf($component))->toBe("\u{FEFF}Customer,Total\nEva,45\nAna,30\nLuis,25.05\n");
});

it('gives builder tables and summaries the table skeleton and an adjustable height on the dashboard', function (): void {
    $dashboard = dashboard($this->owner);
    $table = builderWidget($this->owner, ['type' => BuilderWidgetType::Table, 'config' => ['source' => 'orders', 'columns' => ['customer']]]);
    $summary = builderWidget($this->owner, ['type' => BuilderWidgetType::Summary, 'config' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'count']]]]);
    $dashboard->items()->create(['widget' => $table->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);
    $dashboard->items()->create(['widget' => $summary->dashboardKey(), 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 4]);

    expect(app(ResolveWidgetSkeleton::class)->handle(BuilderSummaryWidget::class, $summary)['shape'])->toBe('table')
        ->and(app(ResolveWidgetSkeleton::class)->handle(BuilderTableWidget::class, $table)['shape'])->toBe('table');

    $html = Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])->html();

    expect(substr_count($html, 'data-skeleton="table"'))->toBe(2)
        ->and(substr_count($html, 'data-adjustable-height'))->toBe(2)
        ->and($html)->toContain('builder-summary-widget')
        ->toContain('builder-table-widget');
});
