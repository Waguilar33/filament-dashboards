<?php

declare(strict_types=1);

namespace Workbench\App\Widgets;

use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\SystemDashboards\SystemWidget;

final class SalesThisYear extends SystemWidget
{
    public function key(): string
    {
        return 'sales-this-year';
    }

    public function name(): string
    {
        return 'Sales at a glance';
    }

    public function type(): BuilderWidgetType
    {
        return BuilderWidgetType::Stats;
    }

    public function config(): array
    {
        return ['cards' => [
            ['source' => 'orders', 'metric' => 'count', 'label' => 'Orders', 'filters' => []],
            ['source' => 'orders', 'metric' => 'sum', 'field' => 'total', 'label' => 'Revenue', 'filters' => [['rules' => [['field' => 'paid', 'operator' => 'is', 'choice' => '1']]]]],
            ['source' => 'orders', 'metric' => 'average', 'field' => 'total', 'label' => 'Average order', 'filters' => []],
            ['source' => 'orders', 'metric' => 'count', 'label' => 'Pending', 'filters' => [['rules' => [['field' => 'status', 'operator' => 'is', 'choice' => 'pending']]]]],
        ]];
    }
}
