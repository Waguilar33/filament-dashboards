<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\DateRange;

class LastThirtyDays extends DatePeriod
{
    public function key(): string
    {
        return '30d';
    }

    public function label(): string
    {
        return __('filament-dashboards::dashboards.builder.card_periods.30d');
    }

    public function range(CarbonImmutable $now): DateRange
    {
        return new DateRange($now->subDays(29)->startOfDay(), $now->endOfDay());
    }
}
