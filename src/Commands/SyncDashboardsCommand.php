<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Commands;

use Filament\Facades\Filament;
use Illuminate\Console\Command;
use Waguilar\FilamentDashboards\Actions\SyncSystemDashboards;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\FilamentDashboardsServiceProvider;

class SyncDashboardsCommand extends Command
{
    protected $signature = 'dashboards:sync';

    protected $description = 'Copy the system dashboards registered on each panel into the database.';

    public function handle(SyncSystemDashboards $sync): int
    {
        foreach (Filament::getPanels() as $panel) {
            if (! $panel->hasPlugin(FilamentDashboardsServiceProvider::NAME)) {
                continue;
            }

            /** @var FilamentDashboardsPlugin $plugin */
            $plugin = $panel->getPlugin(FilamentDashboardsServiceProvider::NAME);

            $sync->handle($panel->getId(), $plugin->getSystemDashboards());

            $this->components->info("Synced {$panel->getId()}: ".count($plugin->getSystemDashboards()).' system dashboards.');
        }

        return self::SUCCESS;
    }
}
