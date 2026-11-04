<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Field;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

final class CentsOrdersSource extends DataSource
{
    public function key(): string
    {
        return 'cents-orders';
    }

    public function label(): string
    {
        return 'Orders in cents';
    }

    /**
     * @return Builder<DemoOrder>
     */
    public function query(Model $user, ?Model $tenant): Builder
    {
        return DemoOrder::query();
    }

    public function fields(): array
    {
        return array_map(
            fn (Field $field): Field => $field->name === 'total' ? $field->divideBy(10) : $field,
            (new OrdersSource)->fields(),
        );
    }
}
