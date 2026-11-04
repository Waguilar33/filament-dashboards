<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\DashboardShare;

trait HasOwnerAndShares
{
    public static function bootHasOwnerAndShares(): void
    {
        static::deleting(function (self $model): void {
            $model->shares()->delete();
        });
    }

    /**
     * @return MorphMany<DashboardShare, $this>
     */
    public function shares(): MorphMany
    {
        return $this->morphMany(DashboardShare::class, 'subject');
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
}
