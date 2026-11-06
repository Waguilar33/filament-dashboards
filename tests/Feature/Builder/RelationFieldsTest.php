<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateBreakdown;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateMetric;
use Waguilar\FilamentDashboards\Builder\Actions\CalculateSummary;
use Waguilar\FilamentDashboards\Builder\Actions\FetchRows;
use Waguilar\FilamentDashboards\Builder\Enums\Metric;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Tests\Fixtures\Family;
use Waguilar\FilamentDashboards\Tests\Fixtures\Line;
use Waguilar\FilamentDashboards\Tests\Fixtures\LinesSource;
use Waguilar\FilamentDashboards\Tests\Fixtures\Product;
use Waguilar\FilamentDashboards\Widgets\BuilderTableWidget;

beforeEach(function (): void {
    Line::createTables();

    $trees = Family::query()->create(['name' => 'Trees']);
    $lights = Family::query()->create(['name' => 'Lights']);
    $pine = Product::query()->create(['name' => 'Pine', 'family_id' => $trees->id]);
    $fir = Product::query()->create(['name' => 'Fir', 'family_id' => $trees->id]);
    $led = Product::query()->create(['name' => 'LED', 'family_id' => $lights->id]);
    $loose = Product::query()->create(['name' => 'Loose', 'family_id' => null]);

    foreach ([[$pine, 100], [$fir, 50], [$led, 30], [$loose, 5]] as [$product, $amount]) {
        Line::query()->create(['product_id' => $product->id, 'shop' => 'north', 'amount' => $amount, 'sold_at' => now()]);
    }

    Line::query()->create(['product_id' => $led->id, 'shop' => 'south', 'amount' => 999, 'sold_at' => now()]);
});

it('groups and sums through a two-step belongs-to chain, keeping the source query scope', function (): void {
    expect(app(CalculateBreakdown::class)->handle(new LinesSource, user(), null, 'product.family.name', Metric::Sum, 'amount', []))
        ->toEqual(['labels' => ['Trees', 'Lights', 'Empty'], 'values' => [150, 30, 5]]);
});

it('filters through a relation chain', function (): void {
    $filters = [[['field' => 'product.family.name', 'operator' => 'is', 'value' => 'Trees']]];

    expect(app(CalculateMetric::class)->handle(new LinesSource, user(), null, Metric::Sum, 'amount', $filters))->toEqual(150)
        ->and(app(CalculateMetric::class)->handle(new LinesSource, user(), null, Metric::Count, null, $filters))->toBe(2);
});

it('reads relation fields as table columns and summary groups', function (): void {
    $rows = app(FetchRows::class)->handle(new LinesSource, user(), null, ['product.name', 'product.family.name', 'amount'], 'amount', 'desc', [], 2);
    $summary = app(CalculateSummary::class)->handle(new LinesSource, user(), null, 'product.family.name', [['metric' => Metric::Count, 'field' => null]], []);

    expect($rows)->toEqual([
        ['product__name' => 'Pine', 'product__family__name' => 'Trees', 'amount' => 100],
        ['product__name' => 'Fir', 'product__family__name' => 'Trees', 'amount' => 50],
    ])
        ->and($summary['rows'][0])->toBe(['group' => 'Trees', 'key' => 'Trees', 'values' => [2]])
        ->and($summary['totals'])->toBe([4]);
});

it('refuses a field that crosses a to-many relationship', function (): void {
    app(CalculateMetric::class)->handle(new LinesSource, user(), null, Metric::Sum, 'product.lines.amount', []);
})->throws(InvalidArgumentException::class, '[product.lines.amount] must reach its attribute through belongs-to or has-one relationships; [lines] is not one.');

it('shows relation fields in a builder table widget', function (): void {
    Filament::setCurrentPanel('demo');
    FilamentDashboardsPlugin::get()->dataSources([LinesSource::class]);
    $this->actingAs(user());

    Livewire::test(BuilderTableWidget::class, ['previewType' => 'table', 'previewConfig' => [
        'source' => 'lines', 'columns' => ['product.name', 'product.family.name'], 'sort_field' => 'amount',
    ]])
        ->assertSeeInOrder(['Product', 'Family'])
        ->assertSeeInOrder(['Pine', 'Trees', 'Fir', 'Trees', 'LED', 'Lights']);
});
