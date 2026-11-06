<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

class NextThirtyDays extends NextDays
{
    protected function days(): int
    {
        return 30;
    }
}
