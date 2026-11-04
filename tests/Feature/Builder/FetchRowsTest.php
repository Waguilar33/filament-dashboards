<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Builder\Actions\FetchRows;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Tests\Fixtures\WebOrdersSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

/**
 * @param  list<string>  $columns
 * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
 * @return list<array<string, mixed>>
 */
function rows(array $columns, ?string $sort = null, string $direction = 'asc', array $filters = [], int $limit = 10, ?DataSource $source = null): array
{
    return app(FetchRows::class)->handle($source ?? new OrdersSource, user(), null, $columns, $sort, $direction, $filters, $limit);
}

beforeEach(function (): void {
    DemoOrder::factory()->create(['number' => 'A', 'channel' => 'web', 'status' => 'paid', 'paid' => true, 'total' => 30]);
    DemoOrder::factory()->create(['number' => 'B', 'channel' => 'store', 'status' => 'pending', 'paid' => false, 'total' => 10]);
    DemoOrder::factory()->create(['number' => 'C', 'channel' => 'web', 'status' => 'shipped', 'paid' => true, 'total' => 20]);
});

it('returns the requested columns in the requested order', function (): void {
    expect(rows(['total', 'number'], 'number')[0])->toEqual(['total' => 30, 'number' => 'A'])
        ->and(array_keys(rows(['total', 'number'])[0]))->toBe(['total', 'number']);
});

it('maps choices and booleans to their labels', function (): void {
    expect(rows(['status', 'channel', 'paid'], 'number')[1])->toBe(['status' => 'Pending', 'channel' => 'Store', 'paid' => 'No']);
});

it('keeps a choice value without an option as it is', function (): void {
    DemoOrder::factory()->create(['number' => 'D', 'status' => 'legacy']);

    expect(rows(['status'], 'number', 'desc', limit: 1))->toBe([['status' => 'legacy']]);
});

it('only returns columns that are fields of the source', function (): void {
    expect(rows(['number', 'id', 'secret', 'customer_id', 'number'], 'number')[0])->toBe(['number' => 'A']);
});

it('returns nothing when no column is a field of the source', function (): void {
    expect(rows(['id', 'secret']))->toBe([])
        ->and(rows([]))->toBe([]);
});

it('sorts in both directions', function (string $direction, array $expected): void {
    expect(array_column(rows(['number'], 'total', $direction), 'number'))->toBe($expected);
})->with([
    'ascending' => ['asc', ['B', 'C', 'A']],
    'descending' => ['desc', ['A', 'C', 'B']],
    'uppercase direction' => ['DESC', ['A', 'C', 'B']],
    'unknown direction falls back to ascending' => ['sideways', ['B', 'C', 'A']],
]);

it('ignores a sort field that is not a field of the source', function (): void {
    expect(array_column(rows(['number'], 'id', 'desc'), 'number'))->toHaveCount(3);
});

it('limits the rows', function (): void {
    expect(rows(['number'], 'number', limit: 2))->toBe([['number' => 'A'], ['number' => 'B']]);
});

it('applies the filters', function (): void {
    $filters = [[['field' => 'paid', 'operator' => 'is', 'value' => true]]];

    expect(array_column(rows(['number'], 'number', filters: $filters), 'number'))->toBe(['A', 'C']);
});

it('stays inside the scoping of the source', function (): void {
    $filters = [[['field' => 'channel', 'operator' => 'is', 'value' => 'store']], [['field' => 'total', 'operator' => 'greater_than', 'value' => 0]]];

    expect(array_column(rows(['number'], 'number', filters: $filters, source: new WebOrdersSource), 'number'))->toBe(['A', 'C']);
});
