<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Waguilar\FilamentDashboards\Models\DashboardItem;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;

class SaveCardWidths
{
    /**
     * @param  array<mixed>  $widths
     */
    public function handle(DashboardItem $item, array $widths): void
    {
        $valid = [];

        foreach ($widths as $key => $width) {
            if (is_int($width) && $width >= 1 && $width <= BuilderStatsWidget::COLUMNS) {
                $valid[(string) $key] = $width;
            }
        }

        $item->update(['options' => [...($item->options ?? []), 'card_widths' => $valid]]);
    }
}
