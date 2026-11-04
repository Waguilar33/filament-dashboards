<?php

declare(strict_types=1);

use Spatie\Permission\Models\Role;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Tests\Fixtures\PlainUser;

it('shows a private dashboard only to its owner', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);

    expect($dashboard->canBeViewedBy($owner))->toBeTrue()
        ->and($dashboard->canBeViewedBy(user()))->toBeFalse();
});

it('shows a dashboard to a user it is shared with', function (): void {
    $dashboard = dashboard(user());
    $reader = user();
    share($dashboard, $reader, ShareLevel::Read);

    expect($dashboard->canBeViewedBy($reader))->toBeTrue();
});

it('shows a dashboard to members of a role it is shared with', function (): void {
    $dashboard = dashboard(user());
    $role = Role::create(['name' => 'sales']);
    $member = user();
    $member->assignRole($role);
    share($dashboard, $role, ShareLevel::Read);

    expect($dashboard->canBeViewedBy($member))->toBeTrue()
        ->and($dashboard->canBeViewedBy(user()))->toBeFalse();
});

it('shows a system dashboard to everyone', function (): void {
    $dashboard = dashboard(attributes: ['system_key' => 'sales']);

    expect($dashboard->canBeViewedBy(user()))->toBeTrue();
});

it('shares with users whose model has no roles', function (): void {
    $dashboard = dashboard(user());
    $plain = PlainUser::query()->findOrFail(user()->getKey());
    share($dashboard, $plain, ShareLevel::Edit);

    expect($dashboard->canBeViewedBy($plain))->toBeTrue()
        ->and($dashboard->canBeEditedBy($plain))->toBeTrue();
});

it('lets the owner and edit shares edit, but not read shares', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    [$editor, $reader] = [user(), user()];
    share($dashboard, $editor, ShareLevel::Edit);
    share($dashboard, $reader, ShareLevel::Read);

    expect($dashboard->canBeEditedBy($owner))->toBeTrue()
        ->and($dashboard->canBeEditedBy($editor))->toBeTrue()
        ->and($dashboard->canBeEditedBy($reader))->toBeFalse();
});

it('lets members of a role with an edit share edit', function (): void {
    $dashboard = dashboard(user());
    $role = Role::create(['name' => 'managers']);
    $member = user();
    $member->assignRole($role);
    share($dashboard, $role, ShareLevel::Edit);

    expect($dashboard->canBeEditedBy($member))->toBeTrue();
});

it('lets nobody edit a system dashboard', function (): void {
    expect(dashboard(attributes: ['system_key' => 'sales'])->canBeEditedBy(user()))->toBeFalse();
});

it('lets only the owner delete and share', function (): void {
    $owner = user();
    $dashboard = dashboard($owner);
    $editor = user();
    share($dashboard, $editor, ShareLevel::Edit);

    expect($dashboard->canBeDeletedBy($owner))->toBeTrue()
        ->and($dashboard->canBeSharedBy($owner))->toBeTrue()
        ->and($dashboard->canBeDeletedBy($editor))->toBeFalse()
        ->and($dashboard->canBeSharedBy($editor))->toBeFalse();
});

it('lets nobody delete a system dashboard', function (): void {
    expect(dashboard(attributes: ['system_key' => 'sales'])->canBeDeletedBy(user()))->toBeFalse();
});

it('scopes dashboards to the panel and tenant, keeping system dashboards panel-wide', function (): void {
    $owner = user();
    $tenant = user();
    $here = dashboard($owner);
    dashboard($owner, ['panel' => 'admin', 'slug' => 'other-panel']);
    $otherTenant = dashboard($owner, ['slug' => 'other-tenant']);
    $otherTenant->tenant()->associate($tenant)->save();
    $system = dashboard(attributes: ['system_key' => 'sales', 'slug' => 'system']);

    expect(Dashboard::query()->inScope('demo', null)->pluck('id')->all())->toBe([$here->id, $system->id])
        ->and(Dashboard::query()->inScope('demo', $tenant)->pluck('id')->all())->toBe([$otherTenant->id, $system->id]);
});
