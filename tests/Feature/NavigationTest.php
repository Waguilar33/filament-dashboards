<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Livewire\Sidebar;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Http\Request;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Http\Middleware\RegisterDashboardNavigation;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\BuilderWidgetResource;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
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

it('looks up dashboard navigation only for requests that draw the navigation', function (array $names, bool $looksUp): void {
    $me = user();
    dashboard($me, ['name' => 'Sales', 'show_in_navigation' => true]);
    $this->actingAs($me);
    Filament::setCurrentPanel('demo');
    $panel = Filament::getPanel('demo');
    $request = Request::create('/livewire/update', 'POST', ['components' => array_map(fn (string $name): array => ['snapshot' => json_encode(['memo' => ['name' => $name]])], $names)]);
    $request->headers->set('X-Livewire', '1');
    app()->instance('request', $request);
    $before = count($panel->getNavigationItems());

    app(RegisterDashboardNavigation::class)->handle($request, fn () => response('ok'));

    expect(count($panel->getNavigationItems()) > $before)->toBe($looksUp);
})->with([
    'a widget update' => [['waguilar.filament-dashboards.widgets.builder-stats-widget'], false],
    'the sidebar' => [[Sidebar::class], true],
]);

it('places the lists where the app wants and lets it change or hide dashboard menu items', function (): void {
    FilamentDashboardsPlugin::get()
        ->navigationGroup('Analytics')
        ->navigationSort(10)
        ->modifyDashboardNavigationItemUsing(fn (NavigationItem $item, Dashboard $dashboard): ?NavigationItem => $dashboard->slug === 'hidden' ? null : $item->badge('new'));
    $me = user();
    $shown = dashboard($me, ['name' => 'Shown', 'show_in_navigation' => true]);
    dashboard($me, ['name' => 'Hidden one', 'slug' => 'hidden', 'show_in_navigation' => true]);

    expect(DashboardResource::getNavigationGroup())->toBe('Analytics')
        ->and(BuilderWidgetResource::getNavigationSort())->toBe(11);

    $this->actingAs($me)
        ->get("/demo/custom-dashboards/{$shown->slug}")
        ->assertOk()
        ->assertSee('Shown')
        ->assertDontSee('Hidden one');
});

it('passes the app grid sizes and stacking width to the grid', function (): void {
    FilamentDashboardsPlugin::get()->gridOptions(['cellHeight' => 30, 'stackBelow' => 768]);
    $me = user();
    $dashboard = dashboard($me);
    $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);

    $this->actingAs($me)
        ->get("/demo/custom-dashboards/{$dashboard->slug}")
        ->assertSee('options: JSON.parse(\'{\u0022cellHeight\u0022:30,\u0022margin\u0022:8,\u0022stackBelow\u0022:768}\')', false);
});
