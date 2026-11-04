<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Builder\Actions\ApplyFilters;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Tests\Fixtures\Record;
use Waguilar\FilamentDashboards\Tests\Fixtures\RecordsSource;
use Waguilar\FilamentDashboards\Tests\Fixtures\WebOrdersSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

/**
 * @param  list<list<array{field: string, operator: string, value?: mixed}>>  $filters
 * @return list<string>
 */
function filteredNumbers(array $filters, ?DataSource $source = null): array
{
    $source ??= new OrdersSource;

    return app(ApplyFilters::class)
        ->handle($source->query(user(), null), $source, $filters)
        ->pluck('number')
        ->sort()
        ->values()
        ->all();
}

beforeEach(function (): void {
    DemoOrder::factory()->create(['number' => 'A', 'customer' => 'Ana Lopez', 'status' => 'paid', 'channel' => 'web', 'total' => 10, 'items_count' => 1, 'paid' => true, 'ordered_at' => '2026-01-10 10:00:00']);
    DemoOrder::factory()->create(['number' => 'B', 'customer' => 'Beto Diaz', 'status' => 'pending', 'channel' => 'store', 'total' => 20, 'items_count' => 2, 'paid' => false, 'ordered_at' => '2026-02-15 23:59:59']);
    DemoOrder::factory()->create(['number' => 'C', 'customer' => 'Carla Ana', 'status' => 'shipped', 'channel' => 'phone', 'total' => 30, 'items_count' => 3, 'paid' => true, 'ordered_at' => '2026-03-20 08:00:00']);
    DemoOrder::factory()->create(['number' => 'D', 'customer' => '', 'status' => 'cancelled', 'channel' => 'web', 'total' => 40, 'items_count' => 4, 'paid' => false, 'ordered_at' => '2026-03-20 18:00:00']);
});

it('leaves the query untouched without filters', function (array $filters): void {
    expect(filteredNumbers($filters))->toBe(['A', 'B', 'C', 'D']);
})->with([
    'no groups' => [[]],
    'an empty group' => [[[]]],
]);

it('applies each operator to the fields it supports', function (string $field, string $operator, mixed $value, array $expected): void {
    $rule = ['field' => $field, 'operator' => $operator, 'value' => $value];

    expect(filteredNumbers([[$rule]]))->toBe($expected);
})->with([
    'number is' => ['total', 'is', 20, ['B']],
    'number is as numeric string' => ['total', 'is', '20', ['B']],
    'number is not' => ['total', 'is_not', 20, ['A', 'C', 'D']],
    'number greater than' => ['total', 'greater_than', 20, ['C', 'D']],
    'number less than' => ['total', 'less_than', 20, ['A']],
    'text is' => ['customer', 'is', 'Beto Diaz', ['B']],
    'text is not' => ['customer', 'is_not', 'Beto Diaz', ['A', 'C', 'D']],
    'text contains' => ['customer', 'contains', 'ana', ['A', 'C']],
    'text is empty' => ['customer', 'is_empty', null, ['D']],
    'text is not empty' => ['customer', 'is_not_empty', null, ['A', 'B', 'C']],
    'date is' => ['ordered_at', 'is', '2026-03-20', ['C', 'D']],
    'date is not' => ['ordered_at', 'is_not', '2026-03-20', ['A', 'B']],
    'date before' => ['ordered_at', 'before', '2026-02-15', ['A']],
    'date after' => ['ordered_at', 'after', '2026-02-15', ['C', 'D']],
    'boolean is true' => ['paid', 'is', true, ['A', 'C']],
    'boolean is false' => ['paid', 'is', false, ['B', 'D']],
    'boolean is not true' => ['paid', 'is_not', true, ['B', 'D']],
    'boolean as string' => ['paid', 'is', '1', ['A', 'C']],
    'choice is' => ['status', 'is', 'paid', ['A']],
    'choice is not' => ['status', 'is_not', 'paid', ['B', 'C', 'D']],
    'choice is not empty' => ['status', 'is_not_empty', null, ['A', 'B', 'C', 'D']],
    'choice is empty' => ['status', 'is_empty', null, []],
    'number is not empty' => ['total', 'is_not_empty', null, ['A', 'B', 'C', 'D']],
    'date is not empty' => ['ordered_at', 'is_not_empty', null, ['A', 'B', 'C', 'D']],
]);

it('joins the rules of a group with and', function (): void {
    $filters = [[
        ['field' => 'channel', 'operator' => 'is', 'value' => 'web'],
        ['field' => 'paid', 'operator' => 'is', 'value' => false],
    ]];

    expect(filteredNumbers($filters))->toBe(['D']);
});

it('joins the groups with or', function (): void {
    $filters = [
        [['field' => 'status', 'operator' => 'is', 'value' => 'paid']],
        [['field' => 'status', 'operator' => 'is', 'value' => 'cancelled']],
    ];

    expect(filteredNumbers($filters))->toBe(['A', 'D']);
});

it('combines and rules inside or groups', function (): void {
    $filters = [
        [
            ['field' => 'channel', 'operator' => 'is', 'value' => 'web'],
            ['field' => 'total', 'operator' => 'greater_than', 'value' => 15],
        ],
        [
            ['field' => 'customer', 'operator' => 'contains', 'value' => 'Carla'],
        ],
    ];

    expect(filteredNumbers($filters))->toBe(['C', 'D']);
});

it('never escapes the scoping of the source with an or group', function (): void {
    $filters = [
        [['field' => 'status', 'operator' => 'is', 'value' => 'pending']],
        [['field' => 'channel', 'operator' => 'is', 'value' => 'store']],
        [['field' => 'total', 'operator' => 'greater_than', 'value' => 0]],
    ];

    expect(filteredNumbers($filters, new WebOrdersSource))->toBe(['A', 'D']);
});

it('ignores rules on fields the source does not expose', function (): void {
    $filters = [[
        ['field' => 'id', 'operator' => 'is', 'value' => 999],
        ['field' => 'secret', 'operator' => 'is', 'value' => 'x'],
        ['field' => 'channel', 'operator' => 'is', 'value' => 'web'],
    ]];

    expect(filteredNumbers($filters))->toBe(['A', 'D']);
});

it('ignores rules with an operator that does not apply to the field type', function (array $rule): void {
    expect(filteredNumbers([[$rule]]))->toBe(['A', 'B', 'C', 'D']);
})->with([
    'contains on a number' => [['field' => 'total', 'operator' => 'contains', 'value' => '1']],
    'before on a number' => [['field' => 'total', 'operator' => 'before', 'value' => 100]],
    'greater than on text' => [['field' => 'customer', 'operator' => 'greater_than', 'value' => 'a']],
    'after on a choice' => [['field' => 'status', 'operator' => 'after', 'value' => '2026-01-01']],
    'is empty on a boolean' => [['field' => 'paid', 'operator' => 'is_empty']],
    'contains on a date' => [['field' => 'ordered_at', 'operator' => 'contains', 'value' => '2026']],
    'an unknown operator' => [['field' => 'total', 'operator' => 'matches', 'value' => 1]],
]);

it('ignores rules whose value does not fit the field', function (array $rule): void {
    expect(filteredNumbers([[$rule]]))->toBe(['A', 'B', 'C', 'D']);
})->with([
    'missing value' => [['field' => 'total', 'operator' => 'is']],
    'non numeric number' => [['field' => 'total', 'operator' => 'is', 'value' => 'abc']],
    'invalid date' => [['field' => 'ordered_at', 'operator' => 'before', 'value' => 'not a date']],
    'invalid boolean' => [['field' => 'paid', 'operator' => 'is', 'value' => 'maybe']],
    'array text' => [['field' => 'customer', 'operator' => 'contains', 'value' => ['x']]],
]);

it('drops a group whose rules are all ignored instead of matching everything', function (): void {
    $filters = [
        [['field' => 'status', 'operator' => 'is', 'value' => 'paid']],
        [['field' => 'secret', 'operator' => 'is', 'value' => 'x']],
    ];

    expect(filteredNumbers($filters))->toBe(['A']);
});

it('treats null values as empty and keeps them for is not', function (): void {
    Record::createTable();
    Record::query()->create(['name' => 'a', 'amount' => 5, 'happened_at' => '2026-01-01 00:00:00']);
    Record::query()->create(['name' => '', 'amount' => null, 'happened_at' => null]);
    Record::query()->create(['name' => null, 'amount' => 10, 'happened_at' => '2026-01-02 00:00:00']);
    $source = new RecordsSource;
    $names = fn (string $field, string $operator, mixed $value = null): array => app(ApplyFilters::class)
        ->handle($source->query(user(), null), $source, [[['field' => $field, 'operator' => $operator, 'value' => $value]]])
        ->pluck('id')
        ->all();

    expect($names('name', 'is_empty'))->toBe([2, 3])
        ->and($names('name', 'is_not_empty'))->toBe([1])
        ->and($names('amount', 'is_empty'))->toBe([2])
        ->and($names('amount', 'is_not_empty'))->toBe([1, 3])
        ->and($names('happened_at', 'is_empty'))->toBe([2])
        ->and($names('name', 'is_not', 'a'))->toBe([2, 3])
        ->and($names('amount', 'is_not', 5))->toBe([2, 3]);
});
