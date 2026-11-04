<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Waguilar\FilamentDashboards\Builders\DashboardBuilder;
use Waguilar\FilamentDashboards\Enums\NavigationPosition;
use Waguilar\FilamentDashboards\Models\Concerns\HasOwnerAndShares;

/**
 * @property int $id
 * @property string $panel
 * @property string|null $tenant_type
 * @property int|string|null $tenant_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property string|null $system_key
 * @property string $name
 * @property string $slug
 * @property bool $show_in_navigation
 * @property string|null $navigation_icon
 * @property string|null $navigation_label
 * @property string|null $navigation_group
 * @property NavigationPosition $navigation_position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseEloquentBuilder(DashboardBuilder::class)]
class Dashboard extends Model
{
    use HasOwnerAndShares;

    protected $guarded = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'show_in_navigation' => false,
        'navigation_position' => 'end',
    ];

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.dashboards');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return HasMany<DashboardItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(DashboardItem::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_in_navigation' => 'boolean',
            'navigation_position' => NavigationPosition::class,
        ];
    }
}
