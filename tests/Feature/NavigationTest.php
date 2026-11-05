<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Workbench\App\Models\Team;

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

it('adds dashboards of the current tenant to the navigation on a tenant panel', function (): void {
    $me = user();
    $team = Team::query()->create(['name' => 'North']);
    $other = Team::query()->create(['name' => 'South']);
    $team->users()->attach($me);
    dashboard($me, ['panel' => 'teams', 'name' => 'Team board', 'show_in_navigation' => true])->tenant()->associate($team)->save();
    dashboard($me, ['panel' => 'teams', 'name' => 'Other board', 'slug' => 'other', 'show_in_navigation' => true])->tenant()->associate($other)->save();
    dashboard($me, ['panel' => 'teams', 'name' => 'Plain', 'slug' => 'plain'])->tenant()->associate($team)->save();

    $this->actingAs($me)
        ->get("/teams/{$team->getKey()}/custom-dashboards/plain")
        ->assertOk()
        ->assertSee('Team board')
        ->assertDontSee('Other board');
});
