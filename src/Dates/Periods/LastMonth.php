<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\DateRange;

class LastMonth extends DatePeriod
{
    public function key(): string
    {
        return 'last_month';
    }

    public function label(): string
    {
        return __('filament-dashboards::dashboards.builder.card_periods.last_month');
    }

    public function range(CarbonImmutable $now): DateRange
    {
        return new DateRange($now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth());
    }

    public function previous(DateRange $range): DateRange
    {
        $start = $range->start->subMonthNoOverflow()->startOfMonth();

        return new DateRange($start, $start->endOfMonth());
    }
}
