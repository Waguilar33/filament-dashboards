<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

class NextNinetyDays extends NextDays
{
    public function days(): int
    {
        return 90;
    }
}
