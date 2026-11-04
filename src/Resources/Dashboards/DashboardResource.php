<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\Dashboards;

use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;
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

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

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
        return Dashboard::query()
            ->inScope(FilamentDashboardsPlugin::currentPanel()->getId(), Filament::getTenant())
            ->visibleTo(self::user());
    }

    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        $user = self::user();
        $allowed = match ($action) {
            'viewAny' => Gate::getPolicyFor(Dashboard::class) === null || parent::getAuthorizationResponse($action)->allowed(),
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
        return $table
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
            ->recordUrl(fn (Dashboard $record): string => static::getUrl('view', ['record' => $record]));
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
