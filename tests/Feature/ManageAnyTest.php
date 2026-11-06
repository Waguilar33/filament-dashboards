<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Actions\TransferOwnership;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Pages\ListBuilderWidgets;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ListDashboards;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('lets managers open, edit, share and delete anyone\'s dashboards, but not system ones', function (): void {
    $manager = user();
    FilamentDashboardsPlugin::get()->canManageAnyUsing(fn (Model $user): bool => $user->is($manager));
    $owner = user();
    $theirs = dashboard($owner);
    $system = dashboard(attributes: ['system_key' => 'sales', 'slug' => 'system']);

    expect($theirs->canBeViewedBy($manager))->toBeTrue()
        ->and($theirs->canBeEditedBy($manager))->toBeTrue()
        ->and($theirs->canBeDeletedBy($manager))->toBeTrue()
        ->and($theirs->canBeSharedBy($manager))->toBeTrue()
        ->and($system->canBeEditedBy($manager))->toBeFalse()
        ->and($theirs->canBeViewedBy(user()))->toBeFalse();

    $this->actingAs($manager);

    Livewire::test(ViewDashboard::class, ['record' => $theirs->slug])->assertOk();
    Livewire::test(ListDashboards::class)
        ->assertCanNotSeeTableRecords([$theirs])
        ->removeTableFilter('mine')
        ->assertCanSeeTableRecords([$theirs]);
});

it('keeps managers out of other panels and companies', function (): void {
    $manager = user();
    FilamentDashboardsPlugin::get()->canManageAnyUsing(fn (): bool => true);
    $elsewhere = dashboard(user(), ['panel' => 'teams', 'slug' => 'elsewhere']);

    expect($elsewhere->canBeEditedBy($manager))->toBeFalse();
});

it('transfers a dashboard and its widget links, leaving the old owner an edit share', function (): void {
    $owner = user();
    $heir = user();
    $dashboard = dashboard($owner);
    share($dashboard, $heir, ShareLevel::Read);
    $mine = builderWidget($owner);
    $theirs = builderWidget($heir);
    app(AddDashboardWidget::class)->handle($dashboard, $mine->dashboardKey(), ['w' => 6, 'h' => 4], $owner);
    $item = $dashboard->items()->create(['widget' => $theirs->dashboardKey(), 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 4]);

    app(TransferOwnership::class)->handle($dashboard, $heir);

    expect($dashboard->refresh()->isOwnedBy($heir))->toBeTrue()
        ->and($dashboard->shares()->whereMorphedTo('shareable', $heir)->exists())->toBeFalse()
        ->and($dashboard->canBeEditedBy($owner))->toBeTrue()
        ->and($item->refresh()->shares_widget)->toBeTrue()
        ->and($mine->canBeEditedBy($heir))->toBeTrue();
});

it('transfers a widget from the list', function (): void {
    $owner = user();
    $heir = user();
    $widget = builderWidget($owner);
    $this->actingAs($owner);

    Livewire::test(ListBuilderWidgets::class)
        ->callTableAction('transfer', $widget, data: ['owner' => $heir->getKey()])
        ->assertHasNoTableActionErrors();

    expect($widget->refresh()->isOwnedBy($heir))->toBeTrue()
        ->and($widget->canBeEditedBy($owner))->toBeTrue();
});
