<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Pages\Concerns;

use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmptyState;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Actions\MoveDashboardItem;
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
            ->filter(fn (DashboardItem $item): bool => isset($widgets[$item->widget]));

        if ($items->isEmpty()) {
            return [
                EmptyState::make(__('filament-dashboards::dashboards.empty.heading'))
                    ->description(__('filament-dashboards::dashboards.empty.description'))
                    ->icon(Heroicon::OutlinedSquares2x2)
                    ->footer($addWidget === null ? [] : [$addWidget]),
            ];
        }

        return [
            Grid::make(['default' => 1, 'lg' => 12])->schema($items->map(fn (DashboardItem $item): Group => Group::make([
                Livewire::make($widgets[$item->widget])->key("dashboard-item-{$item->id}"),
                Actions::make($this->itemActions($dashboard, $item))->key("dashboard-item-{$item->id}-actions")->alignEnd()->visible($editing),
            ])->columnSpan(['default' => 'full', 'lg' => $item->w]))->values()->all()),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function itemActions(Dashboard $dashboard, DashboardItem $item): array
    {
        return [
            Action::make("moveUp{$item->id}")
                ->label(__('filament-dashboards::dashboards.actions.move_up'))
                ->icon(Heroicon::OutlinedArrowUp)
                ->iconButton()
                ->action(fn () => app(MoveDashboardItem::class)->handle($dashboard, $item->id, -1)),
            Action::make("moveDown{$item->id}")
                ->label(__('filament-dashboards::dashboards.actions.move_down'))
                ->icon(Heroicon::OutlinedArrowDown)
                ->iconButton()
                ->action(fn () => app(MoveDashboardItem::class)->handle($dashboard, $item->id, 1)),
            Action::make("resize{$item->id}")
                ->label(__('filament-dashboards::dashboards.actions.resize'))
                ->icon(Heroicon::OutlinedArrowsRightLeft)
                ->iconButton()
                ->modalWidth('md')
                ->fillForm(['w' => $item->w])
                ->schema([
                    Select::make('w')
                        ->label(__('filament-dashboards::dashboards.fields.width'))
                        ->options([
                            3 => __('filament-dashboards::dashboards.widths.quarter'),
                            4 => __('filament-dashboards::dashboards.widths.third'),
                            6 => __('filament-dashboards::dashboards.widths.half'),
                            8 => __('filament-dashboards::dashboards.widths.two_thirds'),
                            12 => __('filament-dashboards::dashboards.widths.full'),
                        ])
                        ->required(),
                ])
                ->action(fn (array $data) => $item->update(['w' => $data['w']])),
            Action::make("remove{$item->id}")
                ->label(__('filament-dashboards::dashboards.actions.remove'))
                ->icon(Heroicon::OutlinedTrash)
                ->color('danger')
                ->iconButton()
                ->action(fn () => $item->delete()),
        ];
    }
}
