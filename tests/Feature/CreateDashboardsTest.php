<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Workbench\App\Models\User;

it('lets every signed-in user create dashboards by default', function (): void {
    expect(FilamentDashboardsPlugin::make()->canCreateDashboards(user()))->toBeTrue();
});

it('lets the create callback decide when there is no policy', function (): void {
    $plugin = FilamentDashboardsPlugin::make()->canCreateDashboardsUsing(fn (User $user): bool => $user->email === 'boss@example.com');

    expect($plugin->canCreateDashboards(user()))->toBeFalse();
});

it('lets the app policy decide over the create callback', function (): void {
    Gate::policy(Dashboard::class, DenyingDashboardPolicy::class);
    $plugin = FilamentDashboardsPlugin::make()->canCreateDashboardsUsing(fn (): bool => true);

    expect($plugin->canCreateDashboards(user()))->toBeFalse();
});

final class DenyingDashboardPolicy
{
    public function create(User $user): bool
    {
        return false;
    }
}
