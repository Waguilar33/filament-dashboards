<?php

declare(strict_types=1);

use Waguilar\FilamentDashboards\Actions\CloneDashboard;
use Waguilar\FilamentDashboards\Enums\ShareLevel;

it('clones a dashboard into a private one owned by the user, with its widgets', function (): void {
    $source = dashboard(user());
    $source->items()->create(['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4, 'options' => ['a' => 1]]);
    share($source, user(), ShareLevel::Edit);
    $me = user();

    $clone = app(CloneDashboard::class)->handle($source, $me, null, 'My sales', 'my-sales');

    expect($clone->isOwnedBy($me))->toBeTrue()
        ->and($clone->name)->toBe('My sales')
        ->and($clone->slug)->toBe('my-sales')
        ->and($clone->shares()->count())->toBe(0)
        ->and($clone->items()->get(['widget', 'x', 'y', 'w', 'h', 'options'])->toArray())
        ->toBe([['widget' => 'orders-overview', 'x' => 0, 'y' => 0, 'w' => 6, 'h' => 4, 'options' => ['a' => 1]]]);
});

it('clones a system dashboard into an editable one', function (): void {
    $me = user();

    $clone = app(CloneDashboard::class)->handle(dashboard(attributes: ['system_key' => 'sales']), $me, null, 'Copy', 'copy');

    expect($clone->isSystem())->toBeFalse()
        ->and($clone->canBeEditedBy($me))->toBeTrue();
});

it('clones into the given tenant', function (): void {
    $tenant = user();

    $clone = app(CloneDashboard::class)->handle(dashboard(user()), user(), $tenant, 'Copy', 'copy');

    expect($clone->tenant()->is($tenant))->toBeTrue();
});
