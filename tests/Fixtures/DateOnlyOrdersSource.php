<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Field;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

final class DateOnlyOrdersSource extends DataSource
{
    public function key(): string
    {
        return 'date-only-orders';
    }

    public function label(): string
    {
        return 'Orders by day';
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
            fn (Field $field): Field => $field->name === 'ordered_at' ? $field->dateOnly() : $field,
            (new OrdersSource)->fields(),
        );
    }
}
