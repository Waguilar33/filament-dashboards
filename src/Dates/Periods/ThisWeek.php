<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

class ThisWeek extends DatePeriod
{
    public function key(): string
    {
        return 'week';
    }

    public function label(): string
    {
        return __('filament-dashboards::dashboards.builder.card_periods.week');
    }

    public function range(CarbonImmutable $now): DateRange
    {
        return new DateRange($now->startOfWeek(FilamentDashboardsPlugin::get()->getWeekStart()), $now->endOfDay());
    }

    public function previous(DateRange $range): DateRange
    {
        return new DateRange($range->start->subWeek(), $range->end->subWeek());
    }
}
