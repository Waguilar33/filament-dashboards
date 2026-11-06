<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardHome;
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

it('sets and removes the home dashboard from its row', function (): void {
    $me = user();
    $first = dashboard($me, ['name' => 'First', 'slug' => 'first']);
    $second = dashboard($me, ['name' => 'Second', 'slug' => 'second']);
    $this->actingAs($me);

    Livewire::test(ListDashboards::class)
        ->assertTableActionVisible('setAsHome', $first)
        ->assertTableActionHidden('removeHome', $first)
        ->callTableAction('setAsHome', $first)
        ->assertTableActionHidden('setAsHome', $first)
        ->assertTableActionVisible('removeHome', $first)
        ->assertTableActionVisible('setAsHome', $second)
        ->callTableAction('removeHome', $first);

    expect(DashboardHome::query()->exists())->toBeFalse();
});

it('shares, configures, duplicates and deletes a dashboard from its row', function (): void {
    $me = user();
    $dashboard = dashboard($me, ['name' => 'Sales', 'slug' => 'sales']);
    $teammate = user();
    $this->actingAs($me);

    Livewire::test(ListDashboards::class)
        ->callTableAction('share', $dashboard, data: ['shares' => [['shareable_type' => $teammate->getMorphClass(), 'shareable_id' => (string) $teammate->getKey(), 'level' => 'read']]])
        ->assertHasNoTableActionErrors()
        ->callTableAction('settings', $dashboard, data: ['name' => 'Renamed'])
        ->assertHasNoTableActionErrors()
        ->callTableAction('duplicate', $dashboard, data: ['name' => 'Copy', 'slug' => 'copy'])
        ->assertHasNoTableActionErrors();

    expect($dashboard->refresh()->name)->toBe('Renamed')
        ->and($dashboard->shares()->sole()->shareable_id)->toEqual($teammate->getKey())
        ->and(Dashboard::query()->where('slug', 'copy')->exists())->toBeTrue();

    Livewire::test(ListDashboards::class)->callTableAction('delete', $dashboard);

    expect(Dashboard::query()->whereKey($dashboard->getKey())->exists())->toBeFalse();
});

it('offers row actions only as far as the user may use them', function (): void {
    $me = user();
    $shared = dashboard(user(), ['name' => 'Shared', 'slug' => 'shared']);
    share($shared, $me, ShareLevel::Read);
    $system = dashboard(attributes: ['name' => 'System', 'slug' => 'system', 'system_key' => 'system']);
    $this->actingAs($me);

    Livewire::test(ListDashboards::class)
        ->assertTableActionVisible('setAsHome', $shared)
        ->assertTableActionVisible('duplicate', $shared)
        ->assertTableActionHidden('share', $shared)
        ->assertTableActionHidden('settings', $shared)
        ->assertTableActionHidden('delete', $shared)
        ->assertTableActionHidden('share', $system)
        ->assertTableActionHidden('settings', $system)
        ->assertTableActionHidden('delete', $system);
});

it('keeps the default dashboards table, with the home toggle and a menu', function (): void {
    $this->actingAs(user());

    $table = Livewire::test(ListDashboards::class)->instance()->getTable();
    $actions = $table->getRecordActions();

    expect(array_map(fn (Action|ActionGroup $action): ?string => $action instanceof Action ? $action->getName() : null, $actions))->toBe(['setAsHome', 'removeHome', null])
        ->and(array_map(fn (Action $action): ?string => $action->getName(), $actions[2]->getActions()))->toBe(['share', 'settings', 'duplicate', 'transfer', 'delete'])
        ->and(array_keys($table->getColumns()))->toBe(['name', 'kind']);
});

it('lets the app take over the dashboards table with the plugin actions', function (): void {
    FilamentDashboardsPlugin::get()->modifyDashboardsTableUsing(fn (Table $table, array $actions): Table => $table
        ->columns([TextColumn::make('name')])
        ->recordActions([
            $actions['setAsHome'],
            $actions['removeHome'],
            $actions['share']->color('gray'),
            ActionGroup::make([$actions['settings'], $actions['duplicate'], $actions['delete']]),
        ]));
    $me = user();
    $dashboard = dashboard($me);
    $this->actingAs($me);

    $page = Livewire::test(ListDashboards::class)
        ->assertTableActionVisible('share', $dashboard)
        ->callTableAction('setAsHome', $dashboard)
        ->assertTableActionVisible('removeHome', $dashboard);

    expect(array_keys($page->instance()->getTable()->getColumns()))->toBe(['name']);
});
