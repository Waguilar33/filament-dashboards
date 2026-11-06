<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Resources\Dashboards\Pages;

use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;

class ListDashboards extends ListRecords
{
    protected static string $resource = DashboardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FilamentDashboardsPlugin::get()->modifyAction(FilamentDashboardsPlugin::get()->modifyCreateDashboardAction(CreateAction::make()
                ->slideOver()
                ->modalWidth(Width::Medium)
                ->mutateDataUsing(fn (array $data): array => [
                    ...$data,
                    'panel' => FilamentDashboardsPlugin::currentPanel()->getId(),
                    'owner_type' => DashboardResource::user()->getMorphClass(),
                    'owner_id' => DashboardResource::user()->getKey(),
                    'tenant_type' => Filament::getTenant()?->getMorphClass(),
                    'tenant_id' => Filament::getTenant()?->getKey(),
                ])
                ->successRedirectUrl(fn (Dashboard $record): string => DashboardResource::getUrl('view', ['record' => $record]))), 'dashboards.create'),
        ];
    }
}
