<?php

declare(strict_types=1);

use Livewire\Livewire;
use Waguilar\FilamentDashboards\Widgets\BuilderSummaryWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    $this->actingAs(user());
    DemoOrderFactory::new()->create(['customer' => 'Ána', 'channel' => 'web', 'total' => 1250.5, 'ordered_at' => now()]);
    DemoOrderFactory::new()->create(['customer' => 'Luis', 'channel' => 'store', 'total' => 10, 'ordered_at' => now()->subDay()]);
});

function exported(string $widget, array $config): string
{
    $component = Livewire::test($widget, ['previewType' => $config['type'], 'previewConfig' => $config['config']]);
    $response = (fn () => $this->exportCsv())->call($component->instance());

    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

it('exports a table widget with its raw values', function (): void {
    $csv = exported(BuilderTableWidget::class, ['type' => 'table', 'config' => ['source' => 'orders', 'columns' => ['customer', 'channel', 'total'], 'sort_field' => 'total', 'sort_direction' => 'desc']]);

    expect($csv)->toStartWith("\u{FEFF}Customer,Channel,Total\n")
        ->toContain("Ána,Web,1250.5\n")
        ->toContain("Luis,Store,10\n");
});

it('exports a summary widget with its totals', function (): void {
    $csv = exported(BuilderSummaryWidget::class, ['type' => 'summary', 'config' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'sum', 'field' => 'total']], 'total_row' => true]]);

    expect($csv)->toContain('Web,1250.5')
        ->toContain('Store,10');
});

it('offers export on the table widgets', function (): void {
    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => ['source' => 'orders', 'columns' => ['customer']]])
        ->assertTableActionExists('export');
});

it('keeps text that looks like a formula from running in a spreadsheet', function (): void {
    DemoOrderFactory::new()->create(['customer' => '=HYPERLINK("http://x")', 'channel' => 'web', 'total' => -5, 'ordered_at' => now()->addMinute()]);

    $csv = exported(BuilderTableWidget::class, ['type' => 'table', 'config' => ['source' => 'orders', 'columns' => ['customer', 'total'], 'sort_field' => 'ordered_at', 'sort_direction' => 'desc', 'limit' => 1]]);

    expect($csv)->toContain("\"'=HYPERLINK(\"\"http://x\"\")\",-5\n");
});
