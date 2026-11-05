<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

final class WebOrdersSource extends DataSource
{
    public function key(): string
    {
        return 'web-orders';
    }

    public function label(): string
    {
        return 'Web orders';
    }

    /**
     * @return Builder<DemoOrder>
     */
    public function query(Model $user, ?Model $tenant): Builder
    {
        return DemoOrder::query()->where('channel', 'web');
    }

    public function fields(): array
    {
        return (new OrdersSource)->fields();
    }
}
