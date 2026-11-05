<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Waguilar\FilamentDashboards\Actions\ResolveDateRange;
use Waguilar\FilamentDashboards\Dates\DateRange;

trait InteractsWithDashboardDates
{
    use InteractsWithPageFilters;

    public function dateRange(): DateRange
    {
        return app(ResolveDateRange::class)->handle($this->pageFilters);
    }
}
