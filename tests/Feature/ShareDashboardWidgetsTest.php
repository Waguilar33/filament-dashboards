<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Actions\AddDashboardWidget;
use Waguilar\FilamentDashboards\Actions\SaveShares;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

function widgetShareLevel(BuilderWidget $widget, Model $user): ?ShareLevel
{
    return $widget->shares()->where('shareable_type', $user->getMorphClass())->where('shareable_id', $user->getKey())->first()?->level;
}

it('shares the owner\'s widgets on a dashboard at the level the dashboard is shared', function (): void {
    $owner = user();
    $reader = user();
    $editor = user();
    $dashboard = dashboard($owner);
    $widget = builderWidget($owner);
    $dashboard->items()->create(['widget' => $widget->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    app(SaveShares::class)->handle($dashboard, [
        ['shareable_type' => $reader->getMorphClass(), 'shareable_id' => $reader->getKey(), 'level' => 'read'],
        ['shareable_type' => $editor->getMorphClass(), 'shareable_id' => $editor->getKey(), 'level' => 'edit'],
    ]);

    expect(widgetShareLevel($widget, $reader))->toBe(ShareLevel::Read)
        ->and(widgetShareLevel($widget, $editor))->toBe(ShareLevel::Edit)
        ->and($widget->canBeViewedBy($reader))->toBeTrue();
});

it('raises a widget share to edit but never lowers it', function (): void {
    $owner = user();
    $up = user();
    $kept = user();
    $dashboard = dashboard($owner);
    $widget = builderWidget($owner);
    share($widget, $up, ShareLevel::Read);
    share($widget, $kept, ShareLevel::Edit);
    $dashboard->items()->create(['widget' => $widget->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    app(SaveShares::class)->handle($dashboard, [
        ['shareable_type' => $up->getMorphClass(), 'shareable_id' => $up->getKey(), 'level' => 'edit'],
        ['shareable_type' => $kept->getMorphClass(), 'shareable_id' => $kept->getKey(), 'level' => 'read'],
    ]);

    expect(widgetShareLevel($widget, $up))->toBe(ShareLevel::Edit)
        ->and(widgetShareLevel($widget, $kept))->toBe(ShareLevel::Edit);
});

it('leaves widgets owned by someone else unshared', function (): void {
    $owner = user();
    $reader = user();
    $dashboard = dashboard($owner);
    $theirs = builderWidget(user());
    $dashboard->items()->create(['widget' => $theirs->dashboardKey(), 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4]);

    app(SaveShares::class)->handle($dashboard, [
        ['shareable_type' => $reader->getMorphClass(), 'shareable_id' => $reader->getKey(), 'level' => 'read'],
    ]);

    expect($theirs->shares()->count())->toBe(0);
});

it('shares a widget added later to a shared dashboard', function (): void {
    $owner = user();
    $reader = user();
    $dashboard = dashboard($owner);
    share($dashboard, $reader, ShareLevel::Read);
    $widget = builderWidget($owner);

    app(AddDashboardWidget::class)->handle($dashboard, $widget->dashboardKey(), ['w' => 6, 'h' => 4]);
    app(AddDashboardWidget::class)->handle($dashboard, 'orders-overview', ['w' => 6, 'h' => 4]);

    expect(widgetShareLevel($widget, $reader))->toBe(ShareLevel::Read);
});
