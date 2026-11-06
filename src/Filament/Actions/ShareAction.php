<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Waguilar\FilamentDashboards\Actions\SaveShares;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardShare;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class ShareAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'share';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $plugin = FilamentDashboardsPlugin::get();
        /** @var Model $viewer */
        $viewer = Filament::auth()->user();
        $userType = $viewer->getMorphClass();
        $roleType = $plugin->getRolesRelationship()?->getRelated()->getMorphClass();

        $this
            ->label(__('filament-dashboards::dashboards.actions.share'))
            ->visible(fn (Dashboard|BuilderWidget $record): bool => $record instanceof Dashboard ? DashboardResource::canShare($record) : BuilderWidgetResource::canShare($record))
            ->slideOver()
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(__('filament-dashboards::dashboards.modals.share.heading'))
            ->modalDescription(__('filament-dashboards::dashboards.modals.share.description'))
            ->modalSubmitActionLabel(__('filament-dashboards::dashboards.actions.save'))
            ->fillForm(fn (Dashboard|BuilderWidget $record): array => [
                'shares' => $record->shares->map(fn (DashboardShare $share): array => [
                    'shareable_type' => $share->shareable_type,
                    'shareable_id' => $share->shareable_id,
                    'level' => $share->level->value,
                ])->all(),
            ])
            ->schema([
                Repeater::make('shares')
                    ->hiddenLabel()
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel(__('filament-dashboards::dashboards.modals.share.add'))
                    ->compact()
                    ->table([
                        ...($roleType === null ? [] : [TableColumn::make(__('filament-dashboards::dashboards.fields.shareable_type'))->markAsRequired()->alignStart()]),
                        TableColumn::make(__('filament-dashboards::dashboards.fields.shareable'))->markAsRequired()->alignStart(),
                        TableColumn::make(__('filament-dashboards::dashboards.fields.level'))->markAsRequired()->alignStart(),
                    ])
                    ->schema([
                        Select::make('shareable_type')
                            ->label(__('filament-dashboards::dashboards.fields.shareable_type'))
                            ->options($roleType === null ? [] : [
                                $userType => __('filament-dashboards::dashboards.shareable_types.user'),
                                $roleType => __('filament-dashboards::dashboards.shareable_types.role'),
                            ])
                            ->default($userType)
                            ->selectablePlaceholder(false)
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('shareable_id', null))
                            ->visible($roleType !== null)
                            ->required(),
                        Select::make('shareable_id')
                            ->label(__('filament-dashboards::dashboards.fields.shareable'))
                            ->options(fn (Get $get): array => ($get('shareable_type') ?? $userType) === $roleType
                                ? $plugin->getShareableRoles(Filament::getTenant())?->pluck('name', $plugin->getShareableRoles(Filament::getTenant())->getModel()->getKeyName())->all() ?? []
                                : [])
                            ->getSearchResultsUsing(fn (Get $get, string $search): array => ($get('shareable_type') ?? $userType) === $roleType
                                ? []
                                : self::userOptions($plugin->searchShareableUsers($plugin->getShareableUsers(Filament::getTenant())->whereKeyNot($viewer->getKey()), $search)->limit(50)->get()))
                            ->getOptionLabelUsing(fn (Get $get, mixed $value): ?string => ($get('shareable_type') ?? $userType) === $roleType
                                ? (is_int($value) || is_string($value) ? ($plugin->getShareableRoles(Filament::getTenant())?->pluck('name', $plugin->getShareableRoles(Filament::getTenant())->getModel()->getKeyName())->map(fn (mixed $name): string => is_scalar($name) ? (string) $name : '')->get($value)) : null)
                                : (is_int($value) || is_string($value) ? self::userOptions($plugin->getShareableUsers(Filament::getTenant())->whereKey($value)->get())[$value] ?? null : null))
                            ->searchable()
                            ->required(),
                        Select::make('level')
                            ->label(__('filament-dashboards::dashboards.fields.level'))
                            ->options([
                                ShareLevel::Read->value => __('filament-dashboards::dashboards.share_levels.read'),
                                ShareLevel::Edit->value => __('filament-dashboards::dashboards.share_levels.edit'),
                            ])
                            ->default(ShareLevel::Read->value)
                            ->selectablePlaceholder(false)
                            ->required(),
                    ]),
            ])
            ->successNotificationTitle(__('filament-dashboards::dashboards.notifications.shares_saved'))
            ->action(function (array $data, Dashboard|BuilderWidget $record, Action $action) use ($userType, $roleType, $plugin): void {
                $shares = [];

                foreach (Arr::array($data, 'shares') as $share) {
                    $id = is_array($share) ? ($share['shareable_id'] ?? null) : null;

                    if (! is_array($share) || (! is_int($id) && ! is_string($id))) {
                        continue;
                    }

                    $shares[] = [
                        'shareable_type' => Arr::string($share + ['shareable_type' => $userType], 'shareable_type'),
                        'shareable_id' => $id,
                        'level' => Arr::string($share, 'level'),
                    ];
                }

                $allowedUsers = $plugin->getShareableUsers(Filament::getTenant())->whereKey(array_column(array_filter($shares, fn (array $share): bool => $share['shareable_type'] === $userType), 'shareable_id'))->pluck($plugin->getShareableUsers(null)->getModel()->getKeyName())->map(fn (mixed $key): string => is_scalar($key) ? (string) $key : '')->all();
                $allowedRoles = $roleType === null ? [] : ($plugin->getShareableRoles(Filament::getTenant())?->pluck($plugin->getShareableRoles(Filament::getTenant())->getModel()->getKeyName())->map(fn (mixed $key): string => is_scalar($key) ? (string) $key : '')->all() ?? []);

                app(SaveShares::class)->handle($record, array_values(array_filter($shares, fn (array $share): bool => in_array((string) $share['shareable_id'], $share['shareable_type'] === $userType ? $allowedUsers : $allowedRoles, true))));

                $action->success();
            });
    }

    /**
     * @param  iterable<Model>  $users
     * @return array<int|string, string>
     */
    protected static function userOptions(iterable $users): array
    {
        $options = [];

        foreach ($users as $user) {
            $key = $user->getKey();

            if (is_int($key) || is_string($key)) {
                $options[$key] = Filament::getUserName($user);
            }
        }

        return $options;
    }
}
