<?php

declare(strict_types=1);

namespace Workbench\App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Pages\Home;
use Workbench\App\Dashboards\OverviewDashboard;
use Workbench\App\DataSources\OrdersSource;
use Workbench\App\Models\User;
use Workbench\App\Widgets\OrdersByChannel;
use Workbench\App\Widgets\RevenueTrend;
use Workbench\App\Widgets\SalesThisYear;

final class DemoPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('demo')
            ->path('demo')
            ->login()
            ->pages([Home::class])
            ->navigationGroups(['Reports', 'Sales'])
            ->discoverWidgets(in: __DIR__.'/../../Filament/Widgets', for: 'Workbench\\App\\Filament\\Widgets')
            ->plugin(FilamentDashboardsPlugin::make()
                ->dataSources([OrdersSource::class])
                ->shareableUsersUsing(fn (?Model $tenant): Builder => User::query())
                ->systemWidgets([SalesThisYear::class, RevenueTrend::class, OrdersByChannel::class])
                ->systemDashboards([OverviewDashboard::class]))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ], isPersistent: true)
            ->authMiddleware([Authenticate::class]);
    }
}
