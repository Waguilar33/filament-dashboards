<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Builder\Actions\CalculateScatter;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Tests\Fixtures\Record;
use Waguilar\FilamentDashboards\Tests\Fixtures\RecordsSource;
use Waguilar\FilamentDashboards\Tests\Fixtures\WebOrdersSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

/**
 * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
 * @return list<array{x: float|int, y: float|int}>
 */
function scatter(string $x = 'items_count', string $y = 'total', array $filters = [], int $limit = 500, ?DataSource $source = null): array
{
    return app(CalculateScatter::class)->handle($source ?? new OrdersSource, user(), null, $x, $y, $filters, $limit);
}

beforeEach(function (): void {
    DemoOrder::factory()->create(['channel' => 'web', 'items_count' => 1, 'total' => 10]);
    DemoOrder::factory()->create(['channel' => 'web', 'items_count' => 2, 'total' => 25.5]);
    DemoOrder::factory()->create(['channel' => 'store', 'items_count' => 3, 'total' => 40]);
});

it('returns the x and y values of every row', function (): void {
    expect(scatter())->toEqual([
        ['x' => 1, 'y' => 10],
        ['x' => 2, 'y' => 25.5],
        ['x' => 3, 'y' => 40],
    ]);
});

it('returns numbers rather than strings', function (): void {
    expect(scatter()[1])->toBe(['x' => 2, 'y' => 25.5]);
});

it('limits the points', function (): void {
    expect(scatter(limit: 2))->toHaveCount(2);
});

it('applies the filters', function (): void {
    $filters = [[['field' => 'channel', 'operator' => 'is', 'value' => 'store']]];

    expect(scatter(filters: $filters))->toEqual([['x' => 3, 'y' => 40]]);
});

it('stays inside the scoping of the source', function (): void {
    $filters = [[['field' => 'channel', 'operator' => 'is', 'value' => 'store']], [['field' => 'total', 'operator' => 'greater_than', 'value' => 0]]];

    expect(scatter(filters: $filters, source: new WebOrdersSource))->toHaveCount(2);
});

it('skips rows missing either value', function (): void {
    Record::createTable();
    Record::query()->create(['amount' => 1, 'other' => 2]);
    Record::query()->create(['amount' => 3, 'other' => null]);
    Record::query()->create(['amount' => null, 'other' => 4]);

    expect(scatter('amount', 'other', source: new RecordsSource))->toEqual([['x' => 1, 'y' => 2]]);
});

it('rejects fields that are not number fields of the source', function (string $x, string $y): void {
    scatter($x, $y);
})->with([
    'text x' => ['customer', 'total'],
    'unknown y' => ['total', 'secret'],
    'date y' => ['total', 'ordered_at'],
])->throws(InvalidArgumentException::class);
