<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Waguilar\FilamentDashboards\Builders\DashboardBuilder;
use Waguilar\FilamentDashboards\Enums\NavigationPosition;
use Waguilar\FilamentDashboards\Enums\ShareLevel;

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
     * @return HasMany<DashboardShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(DashboardShare::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    public function isSystem(): bool
    {
        return filled($this->system_key);
    }

    public function isOwnedBy(Model $user): bool
    {
        return $this->owner()->is($user);
    }

    public function canBeViewedBy(Model $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    public function canBeEditedBy(Model $user): bool
    {
        if ($this->isSystem()) {
            return false;
        }

        return $this->isOwnedBy($user) || $this->shares()->sharedWith($user, ShareLevel::Edit)->exists();
    }

    public function canBeDeletedBy(Model $user): bool
    {
        return ! $this->isSystem() && $this->isOwnedBy($user);
    }

    public function canBeSharedBy(Model $user): bool
    {
        return $this->canBeDeletedBy($user);
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
