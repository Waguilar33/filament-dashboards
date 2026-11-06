<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as FilamentDashboard;
use Filament\Schemas\Schema;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Actions\FindHomeDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Pages\Concerns\HasDashboardDates;
use Waguilar\FilamentDashboards\Pages\Concerns\RendersDashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class Home extends FilamentDashboard
{
    use HasDashboardDates;
    use RendersDashboard;

    protected ?Dashboard $home = null;

    protected bool $homeResolved = false;

    public function getTitle(): string|Htmlable
    {
        return $this->getHome()->name ?? parent::getTitle();
    }

    public function getWidgets(): array
    {
        return array_values(array_filter(
            parent::getWidgets(),
            fn (string|WidgetConfiguration $widget): bool => ! is_subclass_of($widget instanceof WidgetConfiguration ? $widget->widget : $widget, DashboardWidget::class),
        ));
    }

    public function content(Schema $schema): Schema
    {
        $home = $this->getHome();

        if ($home === null) {
            $followers = array_filter($this->getWidgets(), fn (string|WidgetConfiguration $widget): bool => in_array(InteractsWithPageFilters::class, class_uses_recursive($widget instanceof WidgetConfiguration ? $widget->widget : $widget), true));

            return $schema->components([
                ...($followers === [] ? [] : [$this->getFiltersFormContentComponent()]),
                $this->getWidgetsContentComponent(),
            ]);
        }

        return $schema->components($this->dashboardComponents($home, false, null));
    }

    protected function getHeaderActions(): array
    {
        $home = $this->getHome();

        return $home === null ? [] : [
            Action::make('openHome')
                ->label(__('filament-dashboards::dashboards.actions.open'))
                ->color('gray')
                ->url(DashboardResource::getUrl('view', ['record' => $home])),
        ];
    }

    protected function getHome(): ?Dashboard
    {
        if (! $this->homeResolved) {
            /** @var Model $user */
            $user = Filament::auth()->user();

            $this->home = app(FindHomeDashboard::class)->handle($user, FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant());
            $this->homeResolved = true;
        }

        return $this->home;
    }
}
