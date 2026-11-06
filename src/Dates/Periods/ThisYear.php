<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\DateRange;

class ThisYear extends DatePeriod
{
    public function key(): string
    {
        return 'year';
    }

    public function label(): string
    {
        return __('filament-dashboards::dashboards.builder.card_periods.year');
    }

    public function range(CarbonImmutable $now): DateRange
    {
        return new DateRange($now->startOfYear(), $now->endOfDay());
    }

    public function previous(DateRange $range): DateRange
    {
        return new DateRange($range->start->subYearNoOverflow(), $range->end->subYearNoOverflow());
    }
}
