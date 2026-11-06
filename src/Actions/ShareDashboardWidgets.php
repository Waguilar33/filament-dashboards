<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Support\Str;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardShare;

class ShareDashboardWidgets
{
    public function handle(Dashboard $dashboard, ?string $widget = null): void
    {
        $keys = $widget === null ? $dashboard->items()->pluck('widget')->all() : [$widget];

        $ids = array_map(
            fn (string $key): string => Str::after($key, BuilderWidget::KEY_PREFIX),
            array_filter($keys, fn (mixed $key): bool => is_string($key) && str_starts_with($key, BuilderWidget::KEY_PREFIX)),
        );

        if ($ids === [] || $dashboard->shares()->doesntExist()) {
            return;
        }

        $widgets = BuilderWidget::query()
            ->whereKey($ids)
            ->whereNull('system_key')
            ->where('owner_type', $dashboard->owner_type)
            ->where('owner_id', $dashboard->owner_id)
            ->get();

        foreach ($widgets as $builderWidget) {
            foreach ($dashboard->shares as $share) {
                $existing = $builderWidget->shares()
                    ->where('shareable_type', $share->shareable_type)
                    ->where('shareable_id', $share->shareable_id)
                    ->first();

                if ($existing instanceof DashboardShare && ($existing->level === ShareLevel::Edit || $share->level === ShareLevel::Read)) {
                    continue;
                }

                $builderWidget->shares()->updateOrCreate(
                    ['shareable_type' => $share->shareable_type, 'shareable_id' => $share->shareable_id],
                    ['level' => $share->level],
                );
            }
        }
    }
}
