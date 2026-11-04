<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Actions\MoveDashboardItem;

it('leaves the order alone when a widget cannot move further', function (): void {
    $dashboard = dashboard(user());
    $first = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);
    $second = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 1, 'w' => 6, 'h' => 4]);

    app(MoveDashboardItem::class)->handle($dashboard, $first->id, -1);
    app(MoveDashboardItem::class)->handle($dashboard, $second->id, 1);

    expect($dashboard->items()->orderBy('y')->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('moves a widget down', function (): void {
    $dashboard = dashboard(user());
    $first = $dashboard->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 12, 'h' => 2]);
    $second = $dashboard->items()->create(['widget' => 'signups-chart', 'x' => 0, 'y' => 1, 'w' => 6, 'h' => 4]);

    app(MoveDashboardItem::class)->handle($dashboard, $first->id, 1);

    expect($dashboard->items()->orderBy('y')->pluck('id')->all())->toBe([$second->id, $first->id]);
});
