<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Actions\CloneBuilderWidget;
use Waguilar\FilamentDashboards\Actions\SyncSystemDashboards;
use Waguilar\FilamentDashboards\Actions\SyncSystemWidgets;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\OrdersCountWidget;
use Waguilar\FilamentDashboards\Tests\Fixtures\SalesDashboard;

it('follows the same sharing rules as dashboards', function (): void {
    $owner = user();
    $widget = builderWidget($owner);
    [$editor, $reader, $stranger] = [user(), user(), user()];
    share($widget, $editor, ShareLevel::Edit);
    share($widget, $reader, ShareLevel::Read);

    expect($widget->canBeViewedBy($reader))->toBeTrue()
        ->and($widget->canBeViewedBy($stranger))->toBeFalse()
        ->and($widget->canBeEditedBy($editor))->toBeTrue()
        ->and($widget->canBeEditedBy($reader))->toBeFalse()
        ->and($widget->canBeDeletedBy($editor))->toBeFalse()
        ->and($widget->canBeDeletedBy($owner))->toBeTrue();
});

it('clones a widget into a private one owned by the user', function (): void {
    $source = builderWidget(user());
    share($source, user(), ShareLevel::Read);
    $me = user();

    $clone = app(CloneBuilderWidget::class)->handle($source, $me, null, 'My orders');

    expect($clone->isOwnedBy($me))->toBeTrue()
        ->and($clone->name)->toBe('My orders')
        ->and($clone->type)->toBe(BuilderWidgetType::Stats)
        ->and($clone->config)->toBe($source->config)
        ->and($clone->shares()->count())->toBe(0);
});

it('syncs system widgets and lets system dashboards place them', function (): void {
    SalesDashboard::$items = [['widget' => OrdersCountWidget::class, 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 4]];

    app(SyncSystemWidgets::class)->handle('demo', [OrdersCountWidget::class]);
    app(SyncSystemDashboards::class)->handle('demo', [SalesDashboard::class]);

    $widget = BuilderWidget::query()->sole();

    expect($widget->isSystem())->toBeTrue()
        ->and($widget->name)->toBe('Orders count')
        ->and(Dashboard::query()->sole()->items()->sole()->widget)->toBe("custom:{$widget->id}");
});

it('removes system widgets no longer registered', function (): void {
    app(SyncSystemWidgets::class)->handle('demo', [OrdersCountWidget::class]);
    $mine = builderWidget(user());

    app(SyncSystemWidgets::class)->handle('demo', []);

    expect(BuilderWidget::query()->pluck('id')->all())->toBe([$mine->id]);
});

it('names system dashboards and widgets in the current language', function (): void {
    $dashboard = dashboard(attributes: ['system_key' => 'overview', 'name' => 'Stored name']);
    $widget = builderWidget(attributes: ['system_key' => 'orders-by-channel', 'name' => 'Stored name']);
    $gone = dashboard(attributes: ['system_key' => 'gone', 'slug' => 'gone', 'name' => 'Stored name']);
    $mine = dashboard(user(), ['slug' => 'mine', 'name' => 'My name']);

    expect($dashboard->name)->toBe('Overview')
        ->and($widget->name)->toBe('Orders by channel')
        ->and($gone->name)->toBe('Stored name')
        ->and($mine->name)->toBe('My name');
});
