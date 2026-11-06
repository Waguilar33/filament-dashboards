<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\DashboardShare;

/**
 * @extends Builder<DashboardShare>
 */
class DashboardShareBuilder extends Builder
{
    public function sharedWith(Model $user, ?ShareLevel $level = null): static
    {
        $this->where(function (Builder $query) use ($user): void {
            $query->whereMorphedTo('shareable', $user);

            $roles = method_exists($user, 'roles') ? $user->roles() : null;

            if ($roles instanceof BelongsToMany) {
                $role = $roles->getRelated();

                $query->orWhere(fn (Builder $byRole): Builder => $byRole
                    ->where('shareable_type', $role->getMorphClass())
                    ->whereIn('shareable_id', $roles->toBase()->select($role->getQualifiedKeyName())));
            }
        });

        if ($level !== null) {
            $this->where('level', $level);
        }

        return $this;
    }
}
