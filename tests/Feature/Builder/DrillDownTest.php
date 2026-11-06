<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;
use Waguilar\FilamentDashboards\Widgets\BuilderSummaryWidget;
use Workbench\Database\Factories\DemoOrderFactory;

beforeEach(function (): void {
    $this->actingAs(user());
    DemoOrderFactory::new()->create(['number' => 'A-1', 'customer' => 'Ana', 'channel' => 'web', 'status' => 'paid', 'total' => 10, 'ordered_at' => now()]);
    DemoOrderFactory::new()->create(['number' => 'A-2', 'customer' => 'Luis', 'channel' => 'store', 'status' => 'paid', 'total' => 20, 'ordered_at' => now()]);
    DemoOrderFactory::new()->create(['number' => 'A-3', 'customer' => 'Eva', 'channel' => 'web', 'status' => 'pending', 'total' => 30, 'ordered_at' => now()]);
});

function drillRows(object $component): array
{
    $instance = $component->instance();
    $entry = $instance->getSchema($instance->getMountedActionSchemaName())?->getComponent('records');

    return collect($entry?->getState() ?? [])->pluck('customer')->sort()->values()->all();
}

it('opens the records behind a stats card with its own filters', function (): void {
    $stats = Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => [
        ['key' => 'paid', 'source' => 'orders', 'metric' => 'count', 'label' => 'Paid', 'filters' => [['rules' => [['field' => 'status', 'operator' => 'is', 'choice' => 'paid']]]]],
    ]]])
        ->assertSeeHtml("mountAction('drillDown', { card: 'paid' })")
        ->mountAction(TestAction::make('drillDown')->arguments(['card' => 'paid']))
        ->assertActionMounted('drillDown');

    expect(drillRows($stats))->toBe(['Ana', 'Luis']);
});

it('opens the records behind a summary row by its stored value', function (): void {
    $summary = Livewire::test(BuilderSummaryWidget::class, ['previewType' => 'summary', 'previewConfig' => ['source' => 'orders', 'group_field' => 'channel', 'metrics' => [['metric' => 'count']], 'filters' => [['rules' => [['field' => 'status', 'operator' => 'is', 'choice' => 'paid']]]]]])
        ->mountAction(TestAction::make('drillDown')->arguments(['key' => 'web', 'label' => 'Web']));

    expect(drillRows($summary))->toBe(['Ana']);
});

it('offers no drill-down when the source declares no fields for it', function (): void {
    Livewire::test(BuilderStatsWidget::class, ['previewType' => 'stats', 'previewConfig' => ['cards' => [['key' => 'cents', 'source' => 'cents-orders', 'metric' => 'count']]]])
        ->assertDontSeeHtml("mountAction('drillDown'")
        ->call('mountAction', 'drillDown', ['card' => 'cents'])
        ->assertForbidden();
});
