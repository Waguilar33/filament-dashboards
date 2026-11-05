<?php

declare(strict_types=1);

use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
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

it('creates dashboards in a medium slide-over with the form in one column by default', function (): void {
    $this->actingAs(user());

    $page = Livewire::test(ListDashboards::class)->mountAction('create')->instance();

    expect($page->getMountedAction()?->isModalSlideOver())->toBeTrue()
        ->and($page->getMountedAction()?->getModalWidth())->toBe(Width::Medium)
        ->and($page->getSchema($page->getMountedActionSchemaName())?->getColumns('lg'))->toBe(1);
});

it('lets the app modify the create action and the dashboard form', function (): void {
    FilamentDashboardsPlugin::get()
        ->modifyCreateDashboardActionUsing(fn (CreateAction $action): CreateAction => $action->slideOver(false)->modalWidth(Width::TwoExtraLarge))
        ->modifyDashboardFormUsing(fn (Schema $schema): Schema => $schema->columns(2));
    $this->actingAs(user());

    $page = Livewire::test(ListDashboards::class)->mountAction('create')->instance();

    expect($page->getMountedAction()?->isModalSlideOver())->toBeFalse()
        ->and($page->getMountedAction()?->getModalWidth())->toBe(Width::TwoExtraLarge)
        ->and($page->getSchema($page->getMountedActionSchemaName())?->getColumns('lg'))->toBe(2);
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
