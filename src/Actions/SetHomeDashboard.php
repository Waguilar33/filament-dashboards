<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardHome;

class SetHomeDashboard
{
    public function handle(Dashboard $dashboard, Model $user, ?Model $tenant): DashboardHome
    {
        return DashboardHome::query()->updateOrCreate([
            'panel' => $dashboard->panel,
            'tenant_type' => $tenant?->getMorphClass(),
            'tenant_id' => $tenant?->getKey(),
            'user_type' => $user->getMorphClass(),
            'user_id' => $user->getKey(),
        ], [
            'dashboard_id' => $dashboard->getKey(),
        ]);
    }
}
