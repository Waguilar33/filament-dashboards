<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $panel
 * @property string|null $tenant_type
 * @property int|string|null $tenant_id
 * @property string $user_type
 * @property int|string $user_id
 * @property int $dashboard_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DashboardHome extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.homes');
    }
}
