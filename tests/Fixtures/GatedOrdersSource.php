<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Waguilar\FilamentDashboards\Builder\DataSource;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\DemoOrder;

final class GatedOrdersSource extends DataSource
{
    public function key(): string
    {
        return 'gated-orders';
    }

    public function label(): string
    {
        return 'Gated orders';
    }

    public function canView(Model $user, ?Model $tenant): bool
    {
        return Gate::forUser($user)->allows('view-orders');
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
