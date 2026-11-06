<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Dates\DateRange;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;
use Waguilar\FilamentDashboards\Widgets\Stats\StatCardResult;

class BuildDashboardDigest
{
    public function __construct(
        protected EvaluateStatsCards $evaluateStatsCards,
    ) {}

    /**
     * @return array{sections: list<array{name: string, cards: list<StatCardResult>}>, others: list<string>}
     */
    public function handle(Dashboard $dashboard, Model $user, ?Model $tenant, DateRange $range): array
    {
        $plugin = FilamentDashboardsPlugin::get();
        $sources = array_keys($plugin->getDataSourcesFor($user, $tenant));
        $coded = $plugin->getAvailableWidgets(FilamentDashboardsPlugin::currentPanel(), $user);
        $builderWidgets = BuilderWidget::query()
            ->visibleTo($user)
            ->whereIn('id', $dashboard->items()->whereNotNull('builder_widget_id')->select('builder_widget_id'))
            ->get()
            ->filter(fn (BuilderWidget $widget): bool => array_diff($widget->sourceKeys(), $sources) === [])
            ->keyBy('id');

        $sections = [];
        $others = [];

        foreach ($dashboard->items()->orderBy('y')->orderBy('x')->orderBy('id')->get() as $item) {
            /** @var DashboardItem $item */
            $widget = $item->builder_widget_id === null ? null : $builderWidgets->get($item->builder_widget_id);

            if ($widget instanceof BuilderWidget && $widget->type === BuilderWidgetType::Stats) {
                $cards = $this->evaluateStatsCards->handle(is_array($widget->config['cards'] ?? null) ? $widget->config['cards'] : [], $user, $tenant, $range);
                $sections[] = ['name' => $widget->name, 'cards' => array_values(array_filter($cards, fn (StatCardResult $card): bool => ! $card->needsAttention))];
            } elseif ($widget instanceof BuilderWidget) {
                $others[] = $widget->name;
            } elseif (isset($coded[$item->widget])) {
                $others[] = $coded[$item->widget]::dashboardLabel();
            }
        }

        return ['sections' => array_values(array_filter($sections, fn (array $section): bool => $section['cards'] !== [])), 'others' => array_values(array_unique($others))];
    }
}
