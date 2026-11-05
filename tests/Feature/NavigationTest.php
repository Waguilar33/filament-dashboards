<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

it('adds dashboards marked for navigation to the user navigation', function (): void {
    $me = user();
    dashboard($me, ['name' => 'Sales', 'show_in_navigation' => true, 'navigation_label' => 'Sales board', 'navigation_group' => 'Reports']);
    dashboard($me, ['name' => 'Hidden', 'slug' => 'hidden']);
    dashboard(user(), ['name' => 'Someone else', 'slug' => 'other', 'show_in_navigation' => true]);

    $this->actingAs($me)
        ->get('/demo/custom-dashboards')
        ->assertOk()
        ->assertSee('Sales board')
        ->assertSee('Reports')
        ->assertSee('/demo/custom-dashboards/sales', escape: false)
        ->assertDontSee('Someone else');
});

it('shows the label of a stored navigation group key', function (): void {
    FilamentDashboardsPlugin::get()->navigationGroupsUsing(fn (): array => ['sales' => 'Ventas']);
    $me = user();
    dashboard($me, ['name' => 'Board', 'show_in_navigation' => true, 'navigation_group' => 'sales']);

    $this->actingAs($me)
        ->get('/demo/custom-dashboards')
        ->assertOk()
        ->assertSee('Ventas');
});

it('offers the panel navigation groups by default', function (): void {
    $panel = Filament::getPanel('demo');
    $panel->navigationGroups([NavigationGroup::make()->label('Finance'), 'keyed' => NavigationGroup::make()->label('Keyed label'), 'Plain']);

    expect(FilamentDashboardsPlugin::get()->getNavigationGroups($panel))
        ->toBe(['Reports' => 'Reports', 'Sales' => 'Sales', 'Finance' => 'Finance', 'keyed' => 'Keyed label', 'Plain' => 'Plain']);
});
