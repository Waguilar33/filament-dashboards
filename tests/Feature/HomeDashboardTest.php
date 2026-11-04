<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Actions\FindHomeDashboard;
use Waguilar\FilamentDashboards\Actions\SetHomeDashboard;
use Waguilar\FilamentDashboards\Enums\ShareLevel;

it('has no home until one is set', function (): void {
    expect(app(FindHomeDashboard::class)->handle(user(), 'demo', null))->toBeNull();
});

it('keeps one home per user, panel and tenant', function (): void {
    $me = user();
    $first = dashboard($me);
    $second = dashboard($me, ['slug' => 'second']);

    app(SetHomeDashboard::class)->handle($first, $me, null);
    app(SetHomeDashboard::class)->handle($second, $me, null);

    expect(app(FindHomeDashboard::class)->handle($me, 'demo', null)?->id)->toBe($second->id)
        ->and(app(FindHomeDashboard::class)->handle($me, 'demo', user()))->toBeNull()
        ->and(app(FindHomeDashboard::class)->handle($me, 'admin', null))->toBeNull();
});

it('drops a home the user can no longer see', function (): void {
    $dashboard = dashboard(user());
    $me = user();
    share($dashboard, $me, ShareLevel::Read);
    app(SetHomeDashboard::class)->handle($dashboard, $me, null);

    $dashboard->shares()->delete();

    expect(app(FindHomeDashboard::class)->handle($me, 'demo', null))->toBeNull();
});

it('removes the home when its dashboard is deleted', function (): void {
    $me = user();
    $dashboard = dashboard($me);
    app(SetHomeDashboard::class)->handle($dashboard, $me, null);

    $dashboard->delete();

    expect(app(FindHomeDashboard::class)->handle($me, 'demo', null))->toBeNull();
});
