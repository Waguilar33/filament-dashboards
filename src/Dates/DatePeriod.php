<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates;

use Carbon\CarbonImmutable;

abstract class DatePeriod
{
    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function range(CarbonImmutable $now): DateRange;

    public function previous(DateRange $range): DateRange
    {
        return $range->previous();
    }
}
