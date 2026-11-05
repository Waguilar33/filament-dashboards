<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets\Stats;

use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStat extends Stat
{
    protected string $view = 'filament-dashboards::stat';

    protected string $cardKey = '';

    protected bool $isResizable = false;

    public function cardKey(string $key): static
    {
        $this->cardKey = $key;

        return $this;
    }

    public function getCardKey(): string
    {
        return $this->cardKey;
    }

    public function resizable(bool $condition = true): static
    {
        $this->isResizable = $condition;

        return $this;
    }

    public function isResizable(): bool
    {
        return $this->isResizable;
    }
}
