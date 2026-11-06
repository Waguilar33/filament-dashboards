<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\FilamentDashboardsIcon;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;

it('uses Heroicons unless the app registers its own icon for an alias', function (): void {
    expect(DashboardResource::getNavigationIcon())->toBe(Heroicon::OutlinedSquares2x2);

    FilamentIcon::register([
        FilamentDashboardsIcon::NAVIGATION_WIDGETS => 'heroicon-o-bolt',
        FilamentDashboardsIcon::PRINT => 'heroicon-o-bolt',
    ]);
    Filament::setCurrentPanel('demo');
    $owner = user();
    $dashboard = dashboard($owner);
    $this->actingAs($owner);

    expect(BuilderWidgetResource::getNavigationIcon())->toBe('heroicon-o-bolt');

    Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertActionHasIcon('print', 'heroicon-o-bolt');
});
