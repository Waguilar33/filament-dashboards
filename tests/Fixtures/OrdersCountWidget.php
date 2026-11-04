<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\SystemDashboards\SystemWidget;

final class OrdersCountWidget extends SystemWidget
{
    public function key(): string
    {
        return 'orders-count';
    }

    public function name(): string
    {
        return 'Orders count';
    }

    public function type(): BuilderWidgetType
    {
        return BuilderWidgetType::Stats;
    }

    public function config(): array
    {
        return ['cards' => [['source' => 'orders', 'metric' => 'count']]];
    }
}
