<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\DateRange;

class RestOfMonth extends DatePeriod
{
    public function key(): string
    {
        return 'rest_of_month';
    }

    public function label(): string
    {
        return __('filament-dashboards::dashboards.builder.card_periods.rest_of_month');
    }

    public function range(CarbonImmutable $now): DateRange
    {
        return new DateRange($now->startOfDay(), $now->endOfMonth());
    }
}
