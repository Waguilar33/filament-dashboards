<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Filament\Actions;

use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
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
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->visible(fn (Dashboard $record): bool => $record->canBeEditedBy(DashboardResource::user()))
            ->slideOver()
            ->modalWidth(Width::Medium)
            ->schema(fn (Schema $schema): Schema => DashboardForm::configure($schema));
    }
}
