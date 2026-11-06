<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Actions\SaveBuilderWidget;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\BuilderWidgetVersion;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Pages\ListBuilderWidgets;
use Waguilar\FilamentDashboards\Resources\Dashboards\Pages\ViewDashboard;

function saveAs(BuilderWidget $widget, string $name, Model $by): void
{
    app(SaveBuilderWidget::class)->handle($widget, BuilderWidgetType::Stats, ['name' => $name, 'cards' => [['source' => 'orders', 'metric' => 'count']]], $by);
}

it('keeps every saved state with who saved it, starting from the state before history began', function (): void {
    $owner = user();
    $widget = builderWidget($owner, ['name' => 'First']);

    saveAs($widget, 'Second', $owner);
    saveAs($widget, 'Third', $owner);

    expect($widget->versions()->orderBy('id')->get()->map(fn (BuilderWidgetVersion $version): array => [$version->name, $version->changed_by_id])->all())
        ->toBe([['First', null], ['Second', $owner->getKey()], ['Third', $owner->getKey()]]);
});

it('keeps only the latest versions and drops them with the widget', function (): void {
    $owner = user();
    $widget = builderWidget($owner);

    foreach (range(1, BuilderWidgetVersion::KEEP + 3) as $index) {
        saveAs($widget, "Name {$index}", $owner);
    }

    expect($widget->versions()->count())->toBe(BuilderWidgetVersion::KEEP)
        ->and($widget->versions()->latest('id')->value('name'))->toBe('Name '.(BuilderWidgetVersion::KEEP + 3));

    $widget->delete();

    expect(BuilderWidgetVersion::query()->count())->toBe(0);
});

it('restores an earlier version from the widgets list', function (): void {
    $owner = user();
    $widget = builderWidget($owner, ['name' => 'First']);
    saveAs($widget, 'Second', $owner);
    $first = $widget->versions()->orderBy('id')->first();
    $this->actingAs($owner);

    Livewire::test(ListBuilderWidgets::class)
        ->callTableAction('history', $widget, data: ['version' => $first->id])
        ->assertHasNoTableActionErrors()
        ->assertNotified(__('filament-dashboards::dashboards.notifications.version_restored'));

    expect($widget->refresh()->name)->toBe('First')
        ->and($widget->versions()->count())->toBe(3);
});

it('offers history only to those who may edit the widget', function (): void {
    $owner = user();
    $reader = user();
    $widget = builderWidget($owner);
    share($widget, $reader, ShareLevel::Read);
    $this->actingAs($reader);

    Livewire::test(ListBuilderWidgets::class)->assertTableActionHidden('history', $widget);
});

it('opens the history of a widget from the dashboard being edited', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $widget = builderWidget($owner, ['name' => 'First']);
    saveAs($widget, 'Second', $owner);
    $dashboard->items()->create(['widget' => $widget->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);
    $this->actingAs($owner);

    Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->assertSeeHtml("mountAction('widgetHistory', { widget: {$widget->id} })")
        ->callAction(TestAction::make('widgetHistory')->arguments(['widget' => $widget->id]), data: ['version' => $widget->versions()->orderBy('id')->value('id')])
        ->assertHasNoActionErrors();

    expect($widget->refresh()->name)->toBe('First');
});

it('says how many dashboards use a widget and saves a copy for this dashboard only', function (): void {
    $owner = user();
    $editor = user();
    $dashboard = dashboard($owner);
    $other = dashboard($owner, ['slug' => 'other']);
    share($dashboard, $editor, ShareLevel::Edit);
    $widget = builderWidget($owner, ['name' => 'Revenue']);
    $item = app(AddDashboardWidget::class)->handle($dashboard, $widget->dashboardKey(), ['w' => 6, 'h' => 4], $owner);
    app(AddDashboardWidget::class)->handle($other, $widget->dashboardKey(), ['w' => 6, 'h' => 4], $owner);
    $this->actingAs($editor);

    $page = Livewire::withQueryParams(['editing' => 1])->test(ViewDashboard::class, ['record' => $dashboard->slug])
        ->mountAction(TestAction::make('editWidget')->arguments(['widget' => $widget->id, 'item' => $item->id]));

    expect($page->instance()->getMountedAction()?->getModalDescription())->toBe('Used on 2 dashboards; saving changes it on all of them.');

    $page->fillForm(['name' => 'My revenue'])
        ->callMountedAction(['copy' => true])
        ->assertHasNoActionErrors();

    $copy = BuilderWidget::query()->where('name', 'My revenue')->sole();

    expect($widget->refresh()->name)->toBe('Revenue')
        ->and($copy->isOwnedBy($editor))->toBeTrue()
        ->and($item->refresh()->widget)->toBe($copy->dashboardKey())
        ->and($item->shares_widget)->toBeTrue()
        ->and($copy->canBeViewedBy($owner))->toBeTrue();
});

it('writes no version when a save changes nothing', function (): void {
    $owner = user();
    $widget = builderWidget($owner);
    saveAs($widget, 'Same', $owner);
    saveAs($widget, 'Same', $owner);

    expect($widget->versions()->count())->toBe(2);
});
