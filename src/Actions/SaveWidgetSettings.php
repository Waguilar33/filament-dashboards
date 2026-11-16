<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Waguilar\FilamentDashboards\Models\DashboardItem;

class SaveWidgetSettings
{
    /**
     * @param  array<mixed>  $settings
     */
    public function handle(DashboardItem $item, array $settings): void
    {
        $item->update(['options' => [...($item->options ?? []), 'settings' => $settings]]);
    }
}
