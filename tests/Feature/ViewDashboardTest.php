<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardHome;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;
use Workbench\App\Models\User;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

function viewAs(User $user, Dashboard $dashboard): mixed
{
    test()->actingAs($user);

    return Livewire::test(ViewDashboard::class, ['record' => $dashboard->slug]);
}

it('shows the dashboard widgets in order', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 1, 'w' => 6, 'h' => 4]);
    $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);

    viewAs($owner, $dashboard)
        ->assertOk()
        ->assertSeeInOrder(['dashboard-item-2', 'dashboard-item-1']);
});

it('shows the empty state when the dashboard has no widgets', function (): void {
    $owner = user();

    viewAs($owner, dashboard($owner))->assertSee('No widgets yet');
});

it('refuses a dashboard the user cannot see', function (): void {
    $dashboard = dashboard(user());

    test()->actingAs(user())->get("/demo/custom-dashboards/{$dashboard->slug}")->assertNotFound();
});

it('gives the owner every action', function (): void {
    $owner = user();

    viewAs($owner, dashboard($owner))
        ->assertActionVisible('addWidget')
        ->assertActionVisible('share')
        ->assertActionVisible('editLayout')
        ->assertActionVisible('settings')
        ->assertActionVisible('clone')
        ->assertActionVisible('setHome')
        ->assertActionVisible('delete');
});

it('lets an edit share change the dashboard but not share or delete it', function (): void {
    $dashboard = dashboard(user());
    $editor = user();
    share($dashboard, $editor, ShareLevel::Edit);

    viewAs($editor, $dashboard)
        ->assertActionVisible('addWidget')
        ->assertActionVisible('settings')
        ->assertActionHidden('share')
        ->assertActionHidden('delete');
});

it('gives a read share and a system dashboard only clone and set as home', function (Closure $make): void {
    $reader = user();
    $dashboard = $make($reader);

    viewAs($reader, $dashboard)
        ->assertActionHidden('addWidget')
        ->assertActionHidden('share')
        ->assertActionHidden('editLayout')
        ->assertActionHidden('settings')
        ->assertActionHidden('delete')
        ->assertActionVisible('clone')
        ->assertActionVisible('setHome');
})->with([
    'read share' => [function (User $reader): Dashboard {
        $dashboard = dashboard(user());
        share($dashboard, $reader, ShareLevel::Read);

        return $dashboard;
    }],
    'system' => [fn (): Dashboard => dashboard(attributes: ['system_key' => 'sales'])],
]);

it('adds a widget at the end with its default size', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    viewAs($owner, $dashboard)->callAction('addWidget', data: ['widget' => 'orders-overview'])->assertHasNoActionErrors();

    expect($dashboard->items()->latest('id')->first()?->only(['widget', 'x', 'y', 'w', 'h']))
        ->toBe(['widget' => 'orders-overview', 'x' => 0, 'y' => 4, 'w' => 6, 'h' => 4]);
});

it('replaces who the dashboard is shared with', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $teammate = user();
    $role = Role::create(['name' => 'sales']);
    share($dashboard, user(), ShareLevel::Read);

    viewAs($owner, $dashboard)->callAction('share', data: ['shares' => [
        ['shareable_type' => $teammate->getMorphClass(), 'shareable_id' => (string) $teammate->getKey(), 'level' => 'edit'],
        ['shareable_type' => $role->getMorphClass(), 'shareable_id' => (string) $role->getKey(), 'level' => 'read'],
    ]])->assertHasNoActionErrors();

    expect($dashboard->shares()->orderBy('id')->get()->map(fn ($share): array => [$share->shareable_type, (int) $share->shareable_id, $share->level])->all())
        ->toBe([[$teammate->getMorphClass(), $teammate->getKey(), ShareLevel::Edit], [$role->getMorphClass(), $role->getKey(), ShareLevel::Read]]);
});

it('fills the share form with the current shares', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $reader = user();
    share($dashboard, $reader, ShareLevel::Read);

    viewAs($owner, $dashboard)
        ->mountAction('share')
        ->assertSchemaStateSet(fn (array $state): bool => array_values($state['shares'])[0]['shareable_id'] === $reader->getKey());
});

it('clones the dashboard and opens the copy', function (): void {
    $me = user();
    $source = dashboard(attributes: ['system_key' => 'sales']);

    viewAs($me, $source)
        ->callAction('clone', data: ['name' => 'Mine', 'slug' => 'mine'])
        ->assertHasNoActionErrors()
        ->assertRedirect('/demo/custom-dashboards/mine');

    expect(Dashboard::query()->where('slug', 'mine')->sole()->isOwnedBy($me))->toBeTrue();
});

it('refuses a slug another dashboard in the panel already uses', function (): void {
    $me = user();
    $source = dashboard($me);
    dashboard(user(), ['slug' => 'taken']);

    viewAs($me, $source)->callAction('clone', data: ['name' => 'Mine', 'slug' => 'taken'])->assertHasActionErrors(['slug']);
});

it('sets the dashboard as the user home', function (): void {
    $me = user();
    $dashboard = dashboard(attributes: ['system_key' => 'sales']);

    viewAs($me, $dashboard)->callAction('setHome')->assertNotified();

    expect(DashboardHome::query()->sole()->dashboard_id)->toBe($dashboard->id);
});

it('updates the settings and follows a changed slug', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);

    viewAs($owner, $dashboard)
        ->callAction('settings', data: ['name' => 'Renamed', 'slug' => 'renamed', 'show_in_navigation' => true, 'navigation_group' => 'Reports'])
        ->assertHasNoActionErrors()
        ->assertRedirect('/demo/custom-dashboards/renamed');

    expect($dashboard->refresh()->only(['name', 'slug', 'show_in_navigation', 'navigation_group']))
        ->toBe(['name' => 'Renamed', 'slug' => 'renamed', 'show_in_navigation' => true, 'navigation_group' => 'Reports']);
});

it('deletes the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);

    viewAs($owner, $dashboard)->callAction('delete')->assertRedirect('/demo/custom-dashboards');

    expect(Dashboard::query()->count())->toBe(0);
});

it('toggles layout editing', function (): void {
    $owner = user();

    viewAs($owner, dashboard($owner))
        ->callAction('editLayout')
        ->assertSet('isEditingLayout', true)
        ->callAction('editLayout')
        ->assertSet('isEditingLayout', false);
});

it('saves the layout from the grid, keeping widgets inside it', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $first = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);
    $second = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 2, 'w' => 6, 'h' => 4]);
    $foreign = dashboard(user(), ['slug' => 'foreign'])->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    viewAs($owner, $dashboard)->call('saveDashboardLayout', [
        ['id' => $first->id, 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 3],
        ['id' => $second->id, 'x' => 10, 'y' => -2, 'w' => 40, 'h' => 99],
        ['id' => $foreign->id, 'x' => 3, 'y' => 3, 'w' => 3, 'h' => 3],
        'junk',
    ]);

    expect($first->refresh()->only(['x', 'y', 'w', 'h']))->toBe(['x' => 6, 'y' => 0, 'w' => 6, 'h' => 3])
        ->and($second->refresh()->only(['x', 'y', 'w', 'h']))->toBe(['x' => 0, 'y' => 0, 'w' => 12, 'h' => 24])
        ->and($foreign->refresh()->only(['x', 'y', 'w', 'h']))->toBe(['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);
});

it('removes a widget from the dashboard', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);

    viewAs($owner, $dashboard)->call('removeDashboardItem', $item->id);

    expect($dashboard->items()->count())->toBe(0);
});

it('refuses layout changes from someone who cannot edit', function (string $method, mixed $argument): void {
    $dashboard = dashboard(user());
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);
    $reader = user();
    share($dashboard, $reader, ShareLevel::Read);

    viewAs($reader, $dashboard)->call($method, $argument === 'item' ? $item->id : [['id' => $item->id, 'x' => 3]])->assertForbidden();

    expect($item->refresh()->x)->toBe(0);
})->with([
    'layout' => ['saveDashboardLayout', 'layout'],
    'remove' => ['removeDashboardItem', 'item'],
]);

it('offers the users the app chooses to share with', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $chosen = user();
    $other = user();
    FilamentDashboardsPlugin::get()->shareableUsersUsing(fn (): Builder => User::query()->whereKey($chosen->getKey()));

    viewAs($owner, $dashboard)
        ->callAction('share', data: ['shares' => [['shareable_type' => $other->getMorphClass(), 'shareable_id' => (string) $other->getKey(), 'level' => 'read']]])
        ->assertHasActionErrors(['shares.0.shareable_id']);
});

it('refuses a widget the user is not offered', function (): void {
    $owner = user();

    viewAs($owner, dashboard($owner))
        ->callAction('addWidget', data: ['widget' => 'hidden-widget'])
        ->assertHasActionErrors(['widget']);
});
