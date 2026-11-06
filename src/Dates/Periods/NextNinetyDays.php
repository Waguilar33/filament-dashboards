<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

class NextNinetyDays extends NextDays
{
    protected function days(): int
    {
        return 90;
    }
}
