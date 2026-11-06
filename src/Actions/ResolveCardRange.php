<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

class ResolveCardRange
{
    public const PERIODS = ['dashboard', 'today', 'week', 'month', 'year', '30d'];

    public function handle(string $period): ?DateRange
    {
        $today = CarbonImmutable::now(FilamentDashboardsPlugin::get()->getTimezone());

        return match ($period) {
            'today' => new DateRange($today->startOfDay(), $today->endOfDay()),
            'week' => new DateRange($today->startOfWeek(), $today->endOfDay()),
            'month' => new DateRange($today->startOfMonth(), $today->endOfDay()),
            'year' => new DateRange($today->startOfYear(), $today->endOfDay()),
            '30d' => new DateRange($today->subDays(29)->startOfDay(), $today->endOfDay()),
            default => null,
        };
    }
}
