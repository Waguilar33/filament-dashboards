<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\SetHomeDashboard;
use Waguilar\FilamentDashboards\Pages\Home;
use Waguilar\FilamentDashboards\Resources\Dashboards\DashboardResource;
use Workbench\App\Filament\Widgets\OrdersOverview;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('shows the user home dashboard', function (): void {
    $me = user();
    $dashboard = dashboard($me, ['name' => 'My home']);
    $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);
    app(SetHomeDashboard::class)->handle($dashboard, $me, null);
    $this->actingAs($me);

    Livewire::test(Home::class)
        ->assertSee('My home')
        ->assertSee("dashboard-item-{$dashboard->items()->sole()->id}")
        ->assertSeeHtml('filtersForm.preset')
        ->assertActionVisible('openHome')
        ->assertActionHasUrl('openHome', DashboardResource::getUrl('view', ['record' => $dashboard]));
});

it('keeps dashboard widgets off the default dashboard', function (): void {
    $this->actingAs(user());

    expect(Livewire::test(Home::class)->instance()->getWidgets())->not->toContain(OrdersOverview::class);
});

it('shows the default dashboard without a home', function (): void {
    $this->actingAs(user());

    Livewire::test(Home::class)
        ->assertSee('Dashboard')
        ->assertDontSee('No widgets yet')
        ->assertDontSeeHtml('filtersForm.preset')
        ->assertActionDoesNotExist('openHome');
});
