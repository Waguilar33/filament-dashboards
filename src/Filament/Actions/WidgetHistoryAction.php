<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\BuilderWidgetVersion;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class WidgetHistoryAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'history';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-dashboards::dashboards.actions.history'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::HISTORY))
            ->visible(fn (?BuilderWidget $record): bool => $record?->canBeEditedBy(DashboardResource::user()) ?? false)
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->modalHeading(__('filament-dashboards::dashboards.modals.history.heading'))
            ->modalDescription(fn (BuilderWidget $record): string => self::versions($record)->isEmpty()
                ? __('filament-dashboards::dashboards.modals.history.empty')
                : __('filament-dashboards::dashboards.modals.history.description', ['current' => self::describe(self::versions($record)->first())]))
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.restore'))
            ->schema(fn (BuilderWidget $record): array => self::versions($record)->count() < 2 ? [] : [
                Radio::make('version')
                    ->hiddenLabel()
                    ->required()
                    ->options(self::versions($record)->skip(1)->mapWithKeys(fn (BuilderWidgetVersion $version): array => [$version->id => self::describe($version)])->all()),
            ])
            ->modalSubmitAction(fn (Action $action, BuilderWidget $record): Action|false => self::versions($record)->count() >= 2 ? $action : false)
            ->action(function (array $data, BuilderWidget $record, Action $action): void {
                $id = $data['version'] ?? null;
                $version = is_numeric($id) ? self::versions($record)->firstWhere('id', (int) $id) : null;

                abort_unless($version instanceof BuilderWidgetVersion, 404);

                app(SaveBuilderWidget::class)->handle($record, $record->type, [
                    ...$version->config,
                    'name' => $version->name,
                    'refresh_interval' => $version->refresh_interval,
                ], DashboardResource::user());

                $action->success();
            })
            ->successNotificationTitle(__('filament-dashboards::dashboards.notifications.version_restored'));
    }

    /**
     * @return Collection<int, BuilderWidgetVersion>
     */
    protected static function versions(BuilderWidget $widget): Collection
    {
        return $widget->versions()->with('changedBy')->latest('id')->get()->toBase();
    }

    protected static function describe(?BuilderWidgetVersion $version): string
    {
        $by = $version?->changedBy;

        return __('filament-dashboards::dashboards.modals.history.version', [
            'who' => $by instanceof Model ? Filament::getUserName($by) : __('filament-dashboards::dashboards.modals.history.before'),
            'when' => $version?->created_at?->diffForHumans() ?? '',
        ]);
    }
}
