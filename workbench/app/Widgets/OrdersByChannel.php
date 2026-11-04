<?php

declare(strict_types=1);

namespace Workbench\App\Widgets;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\SystemDashboards\SystemWidget;

final class OrdersByChannel extends SystemWidget
{
    public function key(): string
    {
        return 'orders-by-channel';
    }

    public function name(): string
    {
        return 'Orders by channel';
    }

    public function type(): BuilderWidgetType
    {
        return BuilderWidgetType::Doughnut;
    }

    public function config(): array
    {
        return ['source' => 'orders', 'group_field' => 'channel', 'metric' => 'count', 'limit' => 10, 'filters' => []];
    }
}
