<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\FindHomeDashboard;
use Waguilar\FilamentDashboards\Actions\SetHomeDashboard;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Pages\Home;
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
        ->assertActionDoesNotExist('openHome')
        ->assertActionHidden('setAsHome')
        ->assertActionVisible('removeHome');
});

it('lists the other dashboards the user can see and shows the picked one in place', function (): void {
    $me = user();
    $owner = user();
    $home = dashboard($me, ['name' => 'My home']);
    $shared = dashboard($owner, ['name' => 'Shared sales', 'slug' => 'shared-sales']);
    share($shared, $me, ShareLevel::Read);
    dashboard($owner, ['name' => 'Private one', 'slug' => 'private-one']);
    app(SetHomeDashboard::class)->handle($home, $me, null);
    $this->actingAs($me);

    $page = Livewire::test(Home::class);
    $group = collect($page->instance()->getCachedHeaderActions())->first(fn (mixed $action): bool => $action instanceof ActionGroup);

    expect($group)->toBeInstanceOf(ActionGroup::class)
        ->and(array_map(fn (Action $action): ?string => $action->getLabel(), $group->getActions()))->toBe(['Shared sales'])
        ->and(array_values($group->getActions())[0]->getUrl())->toBe(Home::getUrl(['dashboard' => 'shared-sales']));

    Livewire::withQueryParams(['dashboard' => 'shared-sales'])->test(Home::class)
        ->assertSee('Shared sales')
        ->assertActionVisible('setAsHome')
        ->assertActionHidden('removeHome')
        ->callAction('setAsHome')
        ->assertRedirect(Home::getUrl());

    expect(app(FindHomeDashboard::class)->handle($me, 'demo', null)?->is($shared))->toBeTrue();
});

it('unpins the home dashboard and keeps showing it', function (): void {
    $me = user();
    $home = dashboard($me, ['name' => 'My home', 'slug' => 'my-home']);
    app(SetHomeDashboard::class)->handle($home, $me, null);
    $this->actingAs($me);

    Livewire::test(Home::class)
        ->callAction('removeHome')
        ->assertRedirect(Home::getUrl(['dashboard' => 'my-home']));

    expect(app(FindHomeDashboard::class)->handle($me, 'demo', null))->toBeNull();
});

it('ignores a picked dashboard the user cannot see', function (): void {
    $me = user();
    dashboard(user(), ['name' => 'Someone else', 'slug' => 'someone-else']);
    $this->actingAs($me);

    Livewire::withQueryParams(['dashboard' => 'someone-else'])->test(Home::class)
        ->assertDontSee('Someone else')
        ->assertActionDoesNotExist('setAsHome');
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
