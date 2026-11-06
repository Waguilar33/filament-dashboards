<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

class NextThirtyDays extends NextDays
{
    public function days(): int
    {
        return 30;
    }
}
