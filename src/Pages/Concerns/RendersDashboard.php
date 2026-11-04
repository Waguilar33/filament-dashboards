<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Pages\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

trait RendersDashboard
{
    /**
     * @return array<Component>
     */
    protected function dashboardComponents(Dashboard $dashboard, bool|Closure $editing, ?Action $addWidget): array
    {
        /** @var Model $user */
        $user = Filament::auth()->user();
        $widgets = FilamentDashboardsPlugin::get()->getAvailableWidgets(FilamentDashboardsPlugin::currentPanel(), $user);

        $items = $dashboard->items()
            ->orderBy('y')
            ->orderBy('x')
            ->orderBy('id')
            ->get()
            ->filter(fn (DashboardItem $item): bool => isset($widgets[$item->widget]))
            ->map(fn (DashboardItem $item): array => [
                'id' => $item->id,
                'widget' => $widgets[$item->widget],
                'x' => $item->x,
                'y' => $item->y,
                'w' => $item->w,
                'h' => $item->h,
            ])
            ->values()
            ->all();

        if ($items === []) {
            return [
                EmptyState::make(__('filament-dashboards::dashboards.empty.heading'))
                    ->description(__('filament-dashboards::dashboards.empty.description'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->footer($addWidget === null ? [] : [$addWidget]),
            ];
        }

        return [
            View::make('filament-dashboards::grid')->viewData(function () use ($items, $editing): array {
                $editable = $editing instanceof Closure ? (bool) $editing() : $editing;

                return [
                    'items' => $items,
                    'editable' => $editable,
                    'signature' => md5((string) json_encode([$items, $editable])),
                ];
            }),
        ];
    }
}
