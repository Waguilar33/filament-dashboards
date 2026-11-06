<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Pages;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as FilamentDashboard;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Waguilar\FilamentDashboards\Actions\FindHomeDashboard;
use Waguilar\FilamentDashboards\Contracts\DashboardWidget;
use Waguilar\FilamentDashboards\Filament\Actions\RemoveHomeAction;
use Waguilar\FilamentDashboards\Filament\Actions\SetHomeAction;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Pages\Concerns\HasDashboardDates;
use Waguilar\FilamentDashboards\Pages\Concerns\RendersDashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class Home extends FilamentDashboard
{
    use HasDashboardDates;
    use RendersDashboard;

    #[Url(as: 'dashboard')]
    public ?string $dashboard = null;

    protected ?Dashboard $shown = null;

    protected bool $shownResolved = false;

    /** @var Collection<int, Dashboard>|null */
    protected ?Collection $pickable = null;

    public function getTitle(): string|Htmlable
    {
        return $this->getShown()->name ?? parent::getTitle();
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
        $home = $this->getShown();

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
        $plugin = FilamentDashboardsPlugin::get();
        $shown = $this->getShown();
        $others = $this->pickable()->reject(fn (Dashboard $dashboard): bool => $dashboard->is($shown));

        return [
            ...($others->isEmpty() ? [] : [ActionGroup::make($others->map(fn (Dashboard $dashboard): Action => Action::make("show{$dashboard->id}")
                ->label($dashboard->name)
                ->url(static::getUrl(['dashboard' => $dashboard->slug])))->values()->all())
                ->label(DashboardResource::getPluralModelLabel())
                ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::PICK_DASHBOARD))
                ->iconPosition(IconPosition::After)
                ->color('gray')
                ->button()]),
            ...($shown === null ? [] : [
                $plugin->modifyAction(SetHomeAction::make()
                    ->record($shown)
                    ->icon(null)
                    ->color('gray')
                    ->after(fn () => $this->redirect(static::getUrl(), navigate: true)), 'home.setAsHome'),
                $plugin->modifyAction(RemoveHomeAction::make()
                    ->record($shown)
                    ->icon(null)
                    ->color('gray')
                    ->after(fn () => $this->redirect(static::getUrl(['dashboard' => $shown->slug]), navigate: true)), 'home.removeHome'),
            ]),
        ];
    }

    protected function getShown(): ?Dashboard
    {
        if (! $this->shownResolved) {
            /** @var Model $user */
            $user = Filament::auth()->user();

            $this->shown = filled($this->dashboard)
                ? $this->pickable()->firstWhere('slug', $this->dashboard)
                : null;
            $this->shown ??= app(FindHomeDashboard::class)->handle($user, FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant());
            $this->shownResolved = true;
        }

        return $this->shown;
    }

    /**
     * @return Collection<int, Dashboard>
     */
    protected function pickable(): Collection
    {
        /** @var Model $user */
        $user = Filament::auth()->user();

        return $this->pickable ??= Dashboard::query()
            ->inScope(FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant())
            ->visibleTo($user)
            ->get()
            ->sortBy(fn (Dashboard $dashboard): string => mb_strtolower($dashboard->name))
            ->values();
    }
}
