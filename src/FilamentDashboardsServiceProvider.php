<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Waguilar\FilamentDashboards\Commands\SyncDashboardsCommand;

final class FilamentDashboardsServiceProvider extends PackageServiceProvider
{
    public const NAME = 'filament-dashboards';

    public const PACKAGE = 'waguilar33/filament-dashboards';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::NAME)
            ->hasConfigFile()
            ->hasViews(self::NAME)
            ->hasTranslations()
            ->hasMigration('create_filament_dashboards_tables')
            ->hasCommand(SyncDashboardsCommand::class);
    }
}
