<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

final class NoExportOrdersSource extends DataSource
{
    public function key(): string
    {
        return 'no-export-orders';
    }

    public function label(): string
    {
        return 'Orders without export';
    }

    public function canExport(Model $user, ?Model $tenant): bool
    {
        return false;
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
        return (new OrdersSource)->fields();
    }
}
