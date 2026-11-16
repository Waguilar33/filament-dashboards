<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Contracts;

use Filament\Schemas\Components\Component;

interface HasDashboardSettings extends DashboardWidget
{
    /**
     * @return array<Component>
     */
    public static function dashboardSettingsSchema(): array;
}
