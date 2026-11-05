<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Dates;

use Carbon\CarbonImmutable;

final readonly class DateRange
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}
}
