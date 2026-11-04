<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentDashboardsPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return FilamentDashboardsServiceProvider::NAME;
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
