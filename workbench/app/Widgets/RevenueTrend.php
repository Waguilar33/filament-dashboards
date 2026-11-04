<?php

declare(strict_types=1);

namespace Workbench\App\Widgets;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\SystemDashboards\SystemWidget;

final class RevenueTrend extends SystemWidget
{
    public function key(): string
    {
        return 'revenue-trend';
    }

    public function name(): string
    {
        return 'Revenue by month';
    }

    public function type(): BuilderWidgetType
    {
        return BuilderWidgetType::Bar;
    }

    public function config(): array
    {
        return ['source' => 'orders', 'date_field' => 'ordered_at', 'metric' => 'sum', 'field' => 'total', 'period' => '12m', 'label' => 'Revenue', 'filters' => []];
    }
}
