<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\Schemas\DashboardForm;

class DashboardSettingsAction extends EditAction
{
    public static function getDefaultName(): ?string
    {
        return 'settings';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->label(__('filament-dashboards::dashboards.actions.settings'))
            ->icon(FilamentDashboardsIcon::get(FilamentDashboardsIcon::SETTINGS))
            ->visible(fn (Dashboard $record): bool => DashboardResource::canEdit($record))
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->schema(fn (Schema $schema): Schema => DashboardForm::configure($schema));
    }
}
