<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\BuilderWidgets;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use UnitEnum;
use Waguilar\FilamentDashboards\Actions\CloneBuilderWidget;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\Builders\BuilderWidgetBuilder;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
use Waguilar\FilamentDashboards\Filament\Actions\TransferOwnershipAction;
use Waguilar\FilamentDashboards\Filament\Actions\WidgetHistoryAction;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Pages\ListBuilderWidgets;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class BuilderWidgetResource extends Resource
{
    protected static ?string $model = BuilderWidget::class;

    protected static ?string $slug = 'custom-dashboard-widgets';

    protected static bool $isScopedToTenant = false;

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return FilamentDashboardsPlugin::get()->getNavigationGroup() ?? parent::getNavigationGroup();
    }

    public static function getNavigationSort(): ?int
    {
        $sort = FilamentDashboardsPlugin::get()->getNavigationSort();

        return $sort === null ? parent::getNavigationSort() : $sort + 1;
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return FilamentDashboardsIcon::get(FilamentDashboardsIcon::NAVIGATION_WIDGETS);
    }

    public static function getModelLabel(): string
    {
        return __('filament-dashboards::dashboards.widgets.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('filament-dashboards::dashboards.widgets.plural_label');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    /**
     * @return Builder<BuilderWidget>
     */
    public static function getEloquentQuery(): Builder
    {
        $query = BuilderWidget::query()->inScope(FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant());

        return FilamentDashboardsPlugin::get()->canManageAny(DashboardResource::user(), Filament::getTenant()) ? $query : $query->visibleTo(DashboardResource::user());
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = DashboardResource::user();
        $allowed = match ($action) {
            'viewAny', 'create' => FilamentDashboardsPlugin::get()->getDataSourcesFor($user, Filament::getTenant()) !== [] && self::policyAllows($action),
            'update' => $record instanceof BuilderWidget && $record->canBeEditedBy($user) && static::canViewAny() && self::policyAllows($action, $record),
            'delete' => $record instanceof BuilderWidget && $record->canBeDeletedBy($user) && static::canViewAny() && self::policyAllows($action, $record),
            default => false,
        };

        return $allowed ? Response::allow() : Response::deny();
    }

    public static function canShare(BuilderWidget $record): bool
    {
        return $record->canBeSharedBy(DashboardResource::user()) && static::canViewAny();
    }

    protected static function policyAllows(string $action, ?BuilderWidget $record = null): bool
    {
        $policy = Gate::getPolicyFor(BuilderWidget::class);

        return ! (is_object($policy) || is_string($policy)) || ! method_exists($policy, $action) || parent::getAuthorizationResponse($action, $record)->allowed();
    }

    public static function table(Table $table): Table
    {
        $actions = self::recordActions();

        return FilamentDashboardsPlugin::get()->modifyWidgetsTable($table
            ->columns([
                TextColumn::make('name')
                    ->label(__('filament-dashboards::dashboards.fields.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('filament-dashboards::dashboards.fields.widget'))
                    ->formatStateUsing(fn (BuilderWidget $record): string => $record->type->getLabel()),
                TextColumn::make('kind')
                    ->label(__('filament-dashboards::dashboards.fields.kind'))
                    ->badge()
                    ->state(fn (BuilderWidget $record): string => match (true) {
                        $record->isSystem() => __('filament-dashboards::dashboards.widgets.kinds.system'),
                        $record->isOwnedBy(DashboardResource::user()) => __('filament-dashboards::dashboards.widgets.kinds.mine'),
                        default => __('filament-dashboards::dashboards.widgets.kinds.shared'),
                    }),
            ])
            ->defaultSort('name')
            ->filters([
                Filter::make('mine')
                    ->label(__('filament-dashboards::dashboards.filters.mine'))
                    ->default()
                    ->visible(fn (): bool => FilamentDashboardsPlugin::get()->canManageAny(DashboardResource::user(), Filament::getTenant()))
                    ->query(fn (BuilderWidgetBuilder $query): BuilderWidgetBuilder => $query->visibleTo(DashboardResource::user())),
            ])
            ->recordAction('edit')
            ->recordActions([
                $actions['edit'],
                ActionGroup::make([$actions['share'], $actions['history'], $actions['duplicate'], $actions['transfer'], $actions['delete']]),
            ]), self::recordActions());
    }

    /**
     * @return array<string, Action>
     */
    protected static function recordActions(): array
    {
        return array_map(fn (Action $action): Action => FilamentDashboardsPlugin::get()->modifyAction($action, "widgets.{$action->getName()}"), [
            'edit' => Action::make('edit')
                ->label(__('filament-dashboards::dashboards.actions.edit'))
                ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::EDIT))
                ->visible(fn (BuilderWidget $record): bool => static::canEdit($record))
                ->slideOver()
                ->modalWidth(Width::FourExtraLarge)
                ->modalDescription(fn (BuilderWidget $record): string => trans_choice('filament-dashboards::dashboards.modals.edit_widget.used_on', $count = $record->placements()->distinct()->count('dashboard_id'), ['count' => $count]))
                ->fillForm(fn (BuilderWidget $record): array => BuilderWidgetForm::fill($record))
                ->schema(fn (BuilderWidget $record): array => BuilderWidgetForm::components($record->type))
                ->action(fn (array $data, BuilderWidget $record): BuilderWidget => app(SaveBuilderWidget::class)->handle($record, $record->type, $data, DashboardResource::user())),
            'duplicate' => Action::make('duplicate')
                ->label(__('filament-dashboards::dashboards.actions.duplicate'))
                ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::DUPLICATE))
                ->visible(fn (): bool => static::canCreate())
                ->modalWidth(Width::Medium)
                ->fillForm(fn (BuilderWidget $record): array => ['name' => __('filament-dashboards::dashboards.modals.clone.name', ['name' => $record->name])])
                ->schema([
                    TextInput::make('name')
                        ->label(__('filament-dashboards::dashboards.fields.name'))
                        ->required()
                        ->maxLength(255),
                ])
                ->action(fn (array $data, BuilderWidget $record): BuilderWidget => app(CloneBuilderWidget::class)->handle($record, DashboardResource::user(), Filament::getTenant(), Arr::string($data, 'name'))),
            'share' => FilamentDashboardsPlugin::get()->modifyShareAction(ShareAction::make()->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::SHARE))),
            'history' => WidgetHistoryAction::make(),
            'transfer' => TransferOwnershipAction::make(),
            'delete' => DeleteAction::make()->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::DELETE)),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuilderWidgets::route('/'),
        ];
    }
}
