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

    public function upTo(CarbonImmutable $now): self
    {
        return new self($this->start, $this->end->greaterThan($now) ? $now : $this->end);
    }

    public function previous(?string $period = null): self
    {
        return match ($period) {
            'today' => new self($this->start->subDay(), $this->end->subDay()),
            'week' => new self($this->start->subWeek(), $this->end->subWeek()),
            'month' => new self($this->start->subMonthNoOverflow(), $this->end->subMonthNoOverflow()),
            'year' => new self($this->start->subYearNoOverflow(), $this->end->subYearNoOverflow()),
            default => $this->previousDays(),
        };
    }

    private function previousDays(): self
    {
        $days = (int) $this->start->startOfDay()->diffInDays($this->end->startOfDay()) + 1;

        return new self($this->start->subDays($days), $this->end->subDays($days));
    }
}
