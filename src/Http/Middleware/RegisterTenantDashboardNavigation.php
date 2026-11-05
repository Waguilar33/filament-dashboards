<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Http\Middleware;

// Its own class: Laravel keeps one copy of a repeated route middleware, at its first spot, before IdentifyTenant.
class RegisterTenantDashboardNavigation extends RegisterDashboardNavigation
{
    protected const RUNS_AFTER_TENANT = true;
}
