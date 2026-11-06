<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

use Carbon\CarbonImmutable;
use Waguilar\FilamentDashboards\Dates\DatePeriod;
use Waguilar\FilamentDashboards\Dates\DateRange;

abstract class NextDays extends DatePeriod
{
    abstract protected function days(): int;

    public function key(): string
    {
        return "next_{$this->days()}d";
    }

    public function label(): string
    {
        return __('filament-dashboards::dashboards.builder.card_periods.next_days', ['count' => $this->days()]);
    }

    public function range(CarbonImmutable $now): DateRange
    {
        return new DateRange($now->startOfDay(), $now->addDays($this->days() - 1)->endOfDay());
    }
}
