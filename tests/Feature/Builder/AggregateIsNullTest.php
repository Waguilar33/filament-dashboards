<?php

declare(strict_types=1);

use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Builder\AggregateIsNull;

it('orders groups with an empty aggregate after the others on every database', function (): void {
    $grammar = new PostgresGrammar(DB::connection());

    expect((new AggregateIsNull('sum', 'orders.total'))->getValue($grammar))->toBe('case when sum("orders"."total") is null then 1 else 0 end')
        ->and((new AggregateIsNull('count', null))->getValue($grammar))->toBe('case when count(*) is null then 1 else 0 end');
});
