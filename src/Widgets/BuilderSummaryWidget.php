<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

class BuilderSummaryWidget extends BuilderTableWidget
{
    protected function boldsTotals(): bool
    {
        return true;
    }
}
