<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Actions\CloneDashboard;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

function place(Dashboard $dashboard, BuilderWidget $widget, Model $by): DashboardItem
{
    return app(AddDashboardWidget::class)->handle($dashboard, $widget->dashboardKey(), ['w' => 6, 'h' => 4], $by);
}

it('lets whoever can open a dashboard see the owner\'s widgets on it, and edit them with an edit share', function (): void {
    $owner = user();
    $reader = user();
    $editor = user();
    $dashboard = dashboard($owner);
    share($dashboard, $reader, ShareLevel::Read);
    share($dashboard, $editor, ShareLevel::Edit);
    $widget = builderWidget($owner);
    place($dashboard, $widget, $owner);

    expect($widget->canBeViewedBy($reader))->toBeTrue()
        ->and($widget->canBeEditedBy($reader))->toBeFalse()
        ->and($widget->canBeViewedBy($editor))->toBeTrue()
        ->and($widget->canBeEditedBy($editor))->toBeTrue()
        ->and($widget->canBeViewedBy(user()))->toBeFalse()
        ->and($widget->shares()->count())->toBe(0);
});

it('takes the access away when the dashboard is unshared or the widget removed from it', function (): void {
    $owner = user();
    $reader = user();
    $dashboard = dashboard($owner);
    share($dashboard, $reader, ShareLevel::Edit);
    $widget = builderWidget($owner);
    $item = place($dashboard, $widget, $owner);

    $dashboard->shares()->delete();
    expect($widget->canBeViewedBy($reader))->toBeFalse()
        ->and($widget->canBeEditedBy($reader))->toBeFalse();

    share($dashboard, $reader, ShareLevel::Edit);
    $item->delete();
    expect($widget->canBeViewedBy($reader))->toBeFalse();
});

it('never widens access to a widget someone else placed', function (): void {
    $owner = user();
    $editor = user();
    $reader = user();
    $dashboard = dashboard($owner);
    share($dashboard, $editor, ShareLevel::Edit);
    share($dashboard, $reader, ShareLevel::Read);
    $widget = builderWidget($owner);
    share($widget, $editor, ShareLevel::Read);

    place($dashboard, $widget, $editor);

    expect($widget->canBeEditedBy($editor))->toBeFalse()
        ->and($widget->canBeViewedBy($reader))->toBeFalse();
});

it('shows a widget its owner placed on someone else\'s dashboard to that dashboard\'s viewers', function (): void {
    $owner = user();
    $editor = user();
    $dashboard = dashboard($owner);
    share($dashboard, $editor, ShareLevel::Edit);
    $theirs = builderWidget($editor);

    place($dashboard, $theirs, $editor);

    expect($theirs->canBeViewedBy($owner))->toBeTrue()
        ->and($theirs->canBeEditedBy($owner))->toBeTrue();
});

it('removes a deleted widget from every dashboard it was on', function (): void {
    $owner = user();
    $widget = builderWidget($owner);
    $first = dashboard($owner);
    $second = dashboard($owner, ['slug' => 'second']);
    place($first, $widget, $owner);
    place($second, $widget, $owner);

    $widget->delete();

    expect(DashboardItem::query()->count())->toBe(0);
});

it('keeps sharing through a cloned dashboard only for widgets the new owner owns', function (): void {
    $owner = user();
    $cloner = user();
    $dashboard = dashboard($owner);
    place($dashboard, builderWidget($owner), $owner);
    place($dashboard, builderWidget($cloner), $cloner);

    $clone = app(CloneDashboard::class)->handle($dashboard->load('items'), $cloner, null, 'Copy', 'copy');

    expect($clone->items()->orderBy('id')->pluck('shares_widget')->all())->toBe([false, true]);
});

it('links existing placements to their widgets when the plugin is upgraded', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $mine = builderWidget($owner);
    $theirs = builderWidget(user());
    $dashboard->items()->createMany([
        ['widget' => $mine->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
        ['widget' => $theirs->dashboardKey(), 'x' => 6, 'y' => 0, 'w' => 6, 'h' => 4],
        ['widget' => 'orders-overview', 'x' => 0, 'y' => 4, 'w' => 6, 'h' => 4],
        ['widget' => 'custom:999', 'x' => 6, 'y' => 4, 'w' => 6, 'h' => 4],
    ]);
    $migration = include __DIR__.'/../../database/migrations/link_filament_dashboards_items_to_widgets.php.stub';
    $migration->down();
    $migration->up();

    expect(DashboardItem::query()->orderBy('id')->get(['builder_widget_id', 'shares_widget'])->map(fn (DashboardItem $item): array => [$item->builder_widget_id, $item->shares_widget])->all())
        ->toBe([[$mine->id, true], [$theirs->id, false], [null, false], [null, false]]);
});
