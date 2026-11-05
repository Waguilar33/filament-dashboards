<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Models\DashboardHome;

class RemoveHomeDashboard
{
    public function handle(Model $user, string $panel, ?Model $tenant): void
    {
        DashboardHome::query()
            ->where('panel', $panel)
            ->where('tenant_type', $tenant?->getMorphClass())
            ->where('tenant_id', $tenant?->getKey())
            ->where('user_type', $user->getMorphClass())
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
