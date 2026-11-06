<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Enums;

enum AlertOperator: string
{
    case Above = 'above';
    case Below = 'below';

    public function getLabel(): string
    {
        return __("filament-dashboards::dashboards.alerts.operators.{$this->value}");
    }
}
