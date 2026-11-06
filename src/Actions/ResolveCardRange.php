<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

class ResolveCardRange
{
    public function handle(string $period): ?DateRange
    {
        $plugin = FilamentDashboardsPlugin::get();

        return $plugin->getDatePeriod($period)?->range(CarbonImmutable::now($plugin->getTimezone()));
    }
}
