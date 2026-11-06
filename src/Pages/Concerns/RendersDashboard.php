<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Pages\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;
use Waguilar\FilamentDashboards\Widgets\BuilderStatsWidget;

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
        $sources = FilamentDashboardsPlugin::get()->getDataSourcesFor($user, Filament::getTenant());

        $builderWidgets = BuilderWidget::query()
            ->where('panel', $dashboard->panel)
            ->visibleTo($user)
            ->whereIn('id', $dashboard->items()
                ->where('widget', 'like', BuilderWidget::KEY_PREFIX.'%')
                ->get(['widget'])
                ->map(fn (DashboardItem $item): string => substr($item->widget, strlen(BuilderWidget::KEY_PREFIX))))
            ->get()
            ->filter(fn (BuilderWidget $widget): bool => array_diff($widget->sourceKeys(), array_keys($sources)) === [])
            ->keyBy(fn (BuilderWidget $widget): string => $widget->dashboardKey());

        $canCreateWidgets = BuilderWidgetResource::canCreate();

        $items = $dashboard->items()
            ->orderBy('y')
            ->orderBy('x')
            ->orderBy('id')
            ->get()
            ->map(function (DashboardItem $item) use ($widgets, $builderWidgets, $user, $canCreateWidgets): ?array {
                $builderWidget = $builderWidgets->get($item->widget);
                $component = $builderWidget instanceof BuilderWidget
                    ? BuilderWidgetForm::component($builderWidget->type)
                    : ($widgets[$item->widget] ?? null);

                if ($component === null) {
                    return null;
                }

                return [
                    'id' => $item->id,
                    'widget' => $component,
                    'props' => $builderWidget instanceof BuilderWidget ? ['builderWidgetId' => $builderWidget->id] : [],
                    'version' => $builderWidget?->updated_at?->timestamp,
                    'builderWidgetId' => $builderWidget instanceof BuilderWidget && $builderWidget->canBeEditedBy($user) ? $builderWidget->id : null,
                    'duplicable' => ! $builderWidget instanceof BuilderWidget || $canCreateWidgets,
                    'label' => $builderWidget instanceof BuilderWidget ? $builderWidget->name : $component::dashboardLabel(),
                    'followsDates' => $builderWidget instanceof BuilderWidget
                        ? $builderWidget->followsDashboardDates()
                        : in_array(InteractsWithPageFilters::class, class_uses_recursive($component), true),
                    'x' => $item->x,
                    'y' => $item->y,
                    'w' => $item->w,
                    'h' => $item->h,
                ];
            })
            ->filter()
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
            ...(array_filter(array_column($items, 'followsDates')) === [] ? [] : [EmbeddedSchema::make('filtersForm')]),
            View::make('filament-dashboards::grid')->viewData(function () use ($items, $editing): array {
                $editable = $editing instanceof Closure ? (bool) $editing() : $editing;
                $cardWidths = $this->cardWidths(array_column(array_filter($items, fn (array $item): bool => $item['widget'] === BuilderStatsWidget::class), 'id'));

                return [
                    'items' => array_map(fn (array $item): array => [...$item, 'props' => [
                        ...$item['props'],
                        ...($item['followsDates'] ? ['pageFilters' => $this->filters] : []),
                        ...($item['widget'] === BuilderStatsWidget::class ? ['cardWidths' => $cardWidths[$item['id']] ?? [], 'resizableCards' => $editable] : []),
                    ]], $items),
                    'editable' => $editable,
                    'signature' => md5((string) json_encode([$items, $editable])),
                ];
            }),
        ];
    }

    /**
     * @param  list<int>  $itemIds
     * @return array<int, array<array-key, int>>
     */
    protected function cardWidths(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        return DashboardItem::query()
            ->whereKey($itemIds)
            ->get()
            ->mapWithKeys(fn (DashboardItem $item): array => [$item->id => $item->cardWidths()])
            ->all();
    }
}
