<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ListDashboards;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('lists the dashboards the user can see, by kind', function (): void {
    $me = user();
    $mine = dashboard($me, ['name' => 'Mine', 'slug' => 'mine']);
    $shared = dashboard(user(), ['name' => 'Shared', 'slug' => 'shared']);
    share($shared, $me, ShareLevel::Read);
    $system = dashboard(attributes: ['name' => 'System', 'slug' => 'system', 'system_key' => 'system']);
    $hidden = dashboard(user(), ['name' => 'Hidden', 'slug' => 'hidden']);
    $this->actingAs($me);

    Livewire::test(ListDashboards::class)
        ->assertCanSeeTableRecords([$mine, $shared, $system])
        ->assertCanNotSeeTableRecords([$hidden])
        ->assertTableColumnStateSet('kind', 'Mine', $mine)
        ->assertTableColumnStateSet('kind', 'Shared with me', $shared)
        ->assertTableColumnStateSet('kind', 'System', $system);
});

it('creates a private dashboard owned by the user in the current panel', function (): void {
    $me = user();
    $this->actingAs($me);

    Livewire::test(ListDashboards::class)
        ->callAction('create', data: ['name' => 'Sales', 'slug' => 'sales'])
        ->assertHasNoActionErrors()
        ->assertRedirect('/demo/custom-dashboards/sales');

    $dashboard = Dashboard::query()->sole();

    expect($dashboard->isOwnedBy($me))->toBeTrue()
        ->and($dashboard->panel)->toBe('demo')
        ->and($dashboard->tenant_id)->toBeNull()
        ->and($dashboard->shares()->count())->toBe(0);
});

it('fills the slug from the name', function (): void {
    $this->actingAs(user());

    Livewire::test(ListDashboards::class)
        ->mountAction('create')
        ->fillForm(['name' => 'Weekly Sales'])
        ->assertSchemaStateSet(['slug' => 'weekly-sales']);
});

it('hides create when the user may not create dashboards', function (): void {
    $this->actingAs(user());
    FilamentDashboardsPlugin::get()->canCreateDashboardsUsing(fn (): bool => false);

    Livewire::test(ListDashboards::class)->assertActionHidden('create');
});
