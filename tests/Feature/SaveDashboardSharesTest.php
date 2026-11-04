<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Actions\SaveDashboardShares;

it('never shares a dashboard with its owner', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);

    app(SaveDashboardShares::class)->handle($dashboard, [
        ['shareable_type' => $owner->getMorphClass(), 'shareable_id' => $owner->getKey(), 'level' => 'edit'],
    ]);

    expect($dashboard->shares()->count())->toBe(0);
});
