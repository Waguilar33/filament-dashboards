<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\Dashboards;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;
use Waguilar\FilamentDashboards\Builders\DashboardBuilder;
use Waguilar\FilamentDashboards\Filament\Actions\DashboardSettingsAction;
use Waguilar\FilamentDashboards\Filament\Actions\DuplicateDashboardAction;
use Waguilar\FilamentDashboards\Filament\Actions\RemoveHomeAction;
use Waguilar\FilamentDashboards\Filament\Actions\SetHomeAction;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
use Waguilar\FilamentDashboards\Filament\Actions\TransferOwnershipAction;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ListDashboards;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\Schemas\DashboardForm;

class DashboardResource extends Resource
{
    protected static ?string $model = Dashboard::class;

    protected static ?string $slug = 'custom-dashboards';

    protected static ?string $recordRouteKeyName = 'slug';

    protected static bool $isScopedToTenant = false;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentDashboardsPlugin::get()->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        $sort = FilamentDashboardsPlugin::get()->getNavigationSort();

        return $sort === null ? parent::getNavigationSort() : $sort;
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return FilamentDashboardsIcon::get(FilamentDashboardsIcon::NAVIGATION_DASHBOARDS);
    }

    public static function getModelLabel(): string
    {
        return __('filament-dashboards::dashboards.resource.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-dashboards::dashboards.resource.plural_label');
    }

    /**
     * @return Builder<Dashboard>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = Dashboard::query()->inScope(FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant());

        return FilamentDashboardsPlugin::get()->canManageAny(self::user(), Filament::getTenant()) ? $query : $query->visibleTo(self::user());
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = self::user();
        $allowed = match ($action) {
            'viewAny' => Gate::getPolicyFor(Dashboard::class) === null
                || parent::getAuthorizationResponse($action)->allowed()
                || FilamentDashboardsPlugin::get()->canCreateDashboards($user)
                || self::getEloquentQuery()->whereNull('system_key')->exists(),
            'create' => FilamentDashboardsPlugin::get()->canCreateDashboards($user),
            'view' => $record instanceof Dashboard && $record->canBeViewedBy($user),
            'update' => $record instanceof Dashboard && $record->canBeEditedBy($user),
            'delete' => $record instanceof Dashboard && $record->canBeDeletedBy($user),
            default => false,
        };

        return $allowed ? Response::allow() : Response::deny();
    }

    public static function form(Schema $schema): Schema
    {
        return DashboardForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        $actions = self::recordActions();

        return FilamentDashboardsPlugin::get()->modifyDashboardsTable($table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-dashboards::dashboards.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kind')
                    ->label(__('filament-dashboards::dashboards.fields.kind'))
                    ->badge()
                    ->state(fn (Dashboard $record): string => match (true) {
                        $record->isSystem() => __('filament-dashboards::dashboards.kinds.system'),
                        $record->isOwnedBy(self::user()) => __('filament-dashboards::dashboards.kinds.mine'),
                        default => __('filament-dashboards::dashboards.kinds.shared'),
                    }),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('mine')
                    ->label(__('filament-dashboards::dashboards.filters.mine'))
                    ->default()
                    ->visible(fn (): bool => FilamentDashboardsPlugin::get()->canManageAny(self::user(), Filament::getTenant()))
                    ->query(fn (DashboardBuilder $query): DashboardBuilder => $query->visibleTo(self::user())),
            ])
            ->recordUrl(fn (Dashboard $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([
                $actions['setAsHome'],
                $actions['removeHome'],
                ActionGroup::make([$actions['share'], $actions['settings'], $actions['duplicate'], $actions['transfer'], $actions['delete']]),
            ]), self::recordActions());
    }

    /**
     * @return array<string, Action>
     */
    protected static function recordActions(): array
    {
        $plugin = FilamentDashboardsPlugin::get();

        return array_map(fn (Action $action): Action => $plugin->modifyAction($action, "dashboards.{$action->getName()}"), [
            'setAsHome' => SetHomeAction::make(),
            'removeHome' => RemoveHomeAction::make(),
            'share' => $plugin->modifyShareAction(ShareAction::make()->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::SHARE))),
            'settings' => $plugin->modifySettingsAction(DashboardSettingsAction::make()),
            'duplicate' => DuplicateDashboardAction::make(),
            'transfer' => TransferOwnershipAction::make(),
            'delete' => DeleteAction::make()
                ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::DELETE))
                ->visible(fn (Dashboard $record): bool => $record->canBeDeletedBy(self::user())),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDashboards::route('/'),
            'view' => ViewDashboard::route('/{record}'),
        ];
    }

    public static function user(): Model
    {
        /** @var Model */
        return Filament::auth()->user();
    }
}
