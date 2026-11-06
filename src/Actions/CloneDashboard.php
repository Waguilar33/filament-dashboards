<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class CloneDashboard
{
    public function handle(Dashboard $source, Model $owner, ?Model $tenant, string $name, string $slug): Dashboard
    {
        return DB::transaction(function () use ($source, $owner, $tenant, $name, $slug): Dashboard {
            $clone = new Dashboard([
                'panel' => $source->panel,
                'name' => $name,
                'slug' => $slug,
                'navigation_position' => $source->navigation_position,
            ]);
            $clone->owner()->associate($owner);
            $clone->tenant()->associate($tenant);
            $clone->save();

            $clone->items()->createMany($source->items->map(fn (DashboardItem $item): array => [
                'widget' => $item->widget,
                'shares_widget' => $item->builderWidget?->isOwnedBy($owner) ?? false,
                'x' => $item->x,
                'y' => $item->y,
                'w' => $item->w,
                'h' => $item->h,
                'options' => $item->options,
            ]));

            return $clone;
        });
    }
}
