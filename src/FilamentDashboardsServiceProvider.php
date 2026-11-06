<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Waguilar\FilamentDashboards\Commands\SendDashboardNotificationsCommand;
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
            ->hasMigrations(['create_filament_dashboards_tables', 'link_filament_dashboards_items_to_widgets', 'create_filament_dashboards_widget_versions_table', 'create_filament_dashboards_notification_tables', 'add_fits_content_to_filament_dashboards_items'])
            ->hasCommands([SyncDashboardsCommand::class, SendDashboardNotificationsCommand::class])
            ->hasInstallCommand(fn (InstallCommand $command): InstallCommand => $command
                ->publishConfigFile()
                ->publishMigrations()
                ->askToRunMigrations()
                ->endWith(function (InstallCommand $command): void {
                    if (class_exists('Waguilar\\FilamentGuardian\\FilamentGuardianPlugin')) {
                        return;
                    }

                    $command->info('Tip: install waguilar33/filament-guardian to choose which roles can add each widget and create dashboards, and to share dashboards with whole roles.');
                }));
    }

    public function packageBooted(): void
    {
        FilamentAsset::register([
            AlpineComponent::make('dashboard-grid', __DIR__.'/../resources/dist/dashboard-grid.js'),
            Css::make('dashboards', __DIR__.'/../resources/dist/dashboards.css'),
        ], self::PACKAGE);
    }
}
