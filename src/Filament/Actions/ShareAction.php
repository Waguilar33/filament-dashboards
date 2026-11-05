<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Waguilar\FilamentDashboards\Actions\SaveShares;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardShare;

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
            ->visible(fn (Dashboard|BuilderWidget $record): bool => $record->canBeSharedBy($viewer))
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
                        ...($roleType === null ? [] : [TableColumn::make(__('filament-dashboards::dashboards.fields.shareable_type'))->markAsRequired()]),
                        TableColumn::make(__('filament-dashboards::dashboards.fields.shareable'))->markAsRequired(),
                        TableColumn::make(__('filament-dashboards::dashboards.fields.level'))->markAsRequired(),
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
                            ->visible($roleType !== null)
                            ->required(),
                        Select::make('shareable_id')
                            ->label(__('filament-dashboards::dashboards.fields.shareable'))
                            ->options(fn (Get $get): array => ($get('shareable_type') ?? $userType) === $roleType
                                ? $plugin->getShareableRoles()?->pluck('name', 'id')->all() ?? []
                                : $plugin->getShareableUsers(Filament::getTenant())
                                    ->whereKeyNot($viewer->getKey())
                                    ->get()
                                    ->mapWithKeys(function (Model $user): array {
                                        $key = $user->getKey();

                                        return is_int($key) || is_string($key) ? [$key => Filament::getUserName($user)] : [];
                                    })
                                    ->all())
                            ->searchable()
                            ->required(),
                        ToggleButtons::make('level')
                            ->label(__('filament-dashboards::dashboards.fields.level'))
                            ->options([
                                ShareLevel::Read->value => __('filament-dashboards::dashboards.share_levels.read'),
                                ShareLevel::Edit->value => __('filament-dashboards::dashboards.share_levels.edit'),
                            ])
                            ->default(ShareLevel::Read->value)
                            ->inline()
                            ->required(),
                    ]),
            ])
            ->action(function (array $data, Dashboard|BuilderWidget $record) use ($userType): void {
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

                app(SaveShares::class)->handle($record, $shares);
            });
    }
}
