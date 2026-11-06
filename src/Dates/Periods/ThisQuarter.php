<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\DateRange;

class ThisQuarter extends DatePeriod
{
    public function key(): string
    {
        return 'quarter';
    }

    public function label(): string
    {
        return __('filament-dashboards::dashboards.builder.card_periods.quarter');
    }

    public function range(CarbonImmutable $now): DateRange
    {
        return new DateRange($now->startOfQuarter(), $now->endOfDay());
    }

    public function previous(DateRange $range): DateRange
    {
        return new DateRange($range->start->subQuarterNoOverflow(), $range->end->subQuarterNoOverflow());
    }
}
