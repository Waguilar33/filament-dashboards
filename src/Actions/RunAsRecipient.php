<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Localizable;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;

class RunAsRecipient
{
    use Localizable;

    /**
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult|null
     */
    public function handle(string $panelId, Model $user, ?Model $tenant, Closure $callback): mixed
    {
        $user = $user->fresh();
        $panel = Filament::getPanel($panelId, isStrict: false);
        $plugin = FilamentDashboardsPlugin::forPanel($panelId);

        if ($panel === null || $plugin === null || ! $user instanceof Model || ! $user instanceof Authenticatable) {
            return null;
        }

        if (($user instanceof FilamentUser && ! $user->canAccessPanel($panel))
            || ($tenant !== null && $user instanceof HasTenants && ! $user->canAccessTenant($tenant))
            || ($panel->hasTenancy() && $tenant === null)) {
            return null;
        }

        $previousPanel = Filament::getCurrentPanel();
        $previousTenant = Filament::getTenant();
        $guard = auth()->guard($panel->getAuthGuard());
        $previousUser = $guard->user();
        $locale = $user instanceof HasLocalePreference ? $user->preferredLocale() : null;

        try {
            Filament::setCurrentPanel($panel);
            $guard->setUser($user);
            Filament::setTenant($tenant, isQuiet: true);

            return $this->withLocale($locale, fn (): mixed => $plugin->inRecipientContext($user, $tenant, $callback));
        } finally {
            Filament::setTenant($previousTenant, isQuiet: true);
            Filament::setCurrentPanel($previousPanel);

            if ($previousUser !== null) {
                $guard->setUser($previousUser);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }
    }
}
