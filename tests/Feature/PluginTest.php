<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

it('registers the plugin on the panel', function (): void {
    expect(Filament::getPanel('demo')->getPlugin('filament-dashboards'))->toBeInstanceOf(FilamentDashboardsPlugin::class);
});
