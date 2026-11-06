<?php

declare(strict_types=1);

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Pages\ListBuilderWidgets;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ListDashboards;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;

beforeEach(function (): void {
    Filament::setCurrentPanel('demo');
});

it('lets the app change any action the plugin builds, by name', function (): void {
    $names = [];
    FilamentDashboardsPlugin::get()->modifyActionUsing(function (Action $action, string $name) use (&$names): Action {
        $names[] = $name;

        return $name === 'dashboard.print' ? $action->label('Imprimir tablero') : $action;
    });
    $owner = user();
    $dashboard = dashboard($owner);
    $widget = builderWidget($owner);
    $dashboard->items()->create(['widget' => $widget->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);
    $this->actingAs($owner);

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertActionHasLabel('print', 'Imprimir tablero');
    Livewire::test(ListDashboards::class)->assertOk();
    Livewire::test(ListBuilderWidgets::class)->assertOk();

    expect(array_unique($names))->toContain('dashboard.edit', 'dashboard.share', 'dashboard.print', 'dashboard.doneEditing', 'dashboard.addWidget', 'dashboards.create', 'dashboards.setAsHome', 'widgets.create', 'widgets.edit', 'widgets.history');
});

it('draws the widget toolbar from the actions, so the hook reaches it', function (): void {
    FilamentDashboardsPlugin::get()->modifyActionUsing(fn (Action $action, string $name): Action => match ($name) {
        'dashboard.removeItem' => $action->label('Quitar del tablero'),
        'dashboard.duplicateItem' => $action->hidden(),
        default => $action,
    });
    $owner = user();
    $dashboard = dashboard($owner);
    $item = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 2]);
    $this->actingAs($owner);

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSee('Quitar del tablero')
        ->assertDontSeeHtml("mountAction('duplicateItem', { item: {$item->id} })");
});
