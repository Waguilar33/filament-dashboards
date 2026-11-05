<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Field;

final class LinesSource extends DataSource
{
    public function key(): string
    {
        return 'lines';
    }

    public function label(): string
    {
        return 'Order lines';
    }

    /**
     * @return Builder<Line>
     */
    public function query(Model $user, ?Model $tenant): Builder
    {
        return Line::query()->where('shop', 'north');
    }

    public function fields(): array
    {
        return [
            Field::text('product.family.name', 'Family'),
            Field::text('product.name', 'Product'),
            Field::number('amount', 'Amount'),
            Field::date('sold_at', 'Sold at'),
            Field::number('product.lines.amount', 'Sibling amounts'),
        ];
    }
}
