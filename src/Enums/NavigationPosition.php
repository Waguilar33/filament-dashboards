<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationPosition: string implements HasLabel
{
    case Start = 'start';
    case End = 'end';

    public function getLabel(): string
    {
        return __("filament-dashboards::dashboards.navigation_positions.{$this->value}");
    }
}
