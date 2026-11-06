<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates\Periods;

class NextSixtyDays extends NextDays
{
    protected function days(): int
    {
        return 60;
    }
}
