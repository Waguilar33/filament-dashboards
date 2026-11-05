<?php

declare(strict_types=1);

namespace Workbench\App\DataSources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Waguilar\FilamentDashboards\Builder\Field;
use Workbench\App\Models\DemoOrder;

final class OrdersSource extends DataSource
{
    public function key(): string
    {
        return 'orders';
    }

    public function label(): string
    {
        return 'Orders';
    }

    public function group(): string
    {
        return 'Shop';
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
        return [
            Field::text('number', 'Number'),
            Field::text('customer', 'Customer'),
            Field::choice('status', 'Status', [
                'pending' => 'Pending',
                'paid' => 'Paid',
                'shipped' => 'Shipped',
                'cancelled' => 'Cancelled',
            ]),
            Field::choice('channel', 'Channel', [
                'store' => 'Store',
                'web' => 'Web',
                'phone' => 'Phone',
            ]),
            Field::number('total', 'Total'),
            Field::number('items_count', 'Items'),
            Field::boolean('paid', 'Paid'),
            Field::date('ordered_at', 'Ordered at'),
        ];
    }
}
