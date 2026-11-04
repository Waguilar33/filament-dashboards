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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use UnitEnum;
use Waguilar\FilamentDashboards\Actions\CloneBuilderWidget;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\Filament\Actions\ShareAction;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

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
        return FilamentDashboardsPlugin::get()->getDataSourcesFor(DashboardResource::user(), Filament::getTenant()) !== [];
    }

    /**
     * @return Builder<BuilderWidget>
     */
    public static function getEloquentQuery(): Builder
    {
        return BuilderWidget::query()
            ->inScope(FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant())
            ->visibleTo(DashboardResource::user());
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = DashboardResource::user();
        $allowed = match ($action) {
            'viewAny' => true,
            'update' => $record instanceof BuilderWidget && $record->canBeEditedBy($user),
            'delete' => $record instanceof BuilderWidget && $record->canBeDeletedBy($user),
            default => false,
        };

        return $allowed ? Response::allow() : Response::deny();
    }

    public static function table(Table $table): Table
    {
        return $table
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
            ->recordActions([
                Action::make('edit')
                    ->label(__('filament-dashboards::dashboards.actions.edit'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->visible(fn (BuilderWidget $record): bool => $record->canBeEditedBy(DashboardResource::user()))
                    ->slideOver()
                    ->modalWidth(Width::FourExtraLarge)
                    ->fillForm(fn (BuilderWidget $record): array => $record->only(['name', 'refresh_interval', 'config']))
                    ->schema(fn (BuilderWidget $record): array => BuilderWidgetForm::components($record->type))
                    ->action(fn (array $data, BuilderWidget $record): BuilderWidget => app(SaveBuilderWidget::class)->handle($record, $record->type, $data)),
                ActionGroup::make([
                    ShareAction::make()->icon(Heroicon::OutlinedShare),
                    Action::make('clone')
                        ->label(__('filament-dashboards::dashboards.actions.clone'))
                        ->icon(Heroicon::OutlinedDocumentDuplicate)
                        ->modalWidth(Width::Medium)
                        ->fillForm(fn (BuilderWidget $record): array => ['name' => __('filament-dashboards::dashboards.modals.clone.name', ['name' => $record->name])])
                        ->schema([
                            TextInput::make('name')
                                ->label(__('filament-dashboards::dashboards.fields.name'))
                                ->required()
                                ->maxLength(255),
                        ])
                        ->action(fn (array $data, BuilderWidget $record): BuilderWidget => app(CloneBuilderWidget::class)->handle($record, DashboardResource::user(), Filament::getTenant(), Arr::string($data, 'name'))),
                    DeleteAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBuilderWidgets::route('/'),
        ];
    }
}
