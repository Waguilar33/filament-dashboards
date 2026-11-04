<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\SystemDashboards\SystemDashboard;

class SyncSystemDashboards
{
    /**
     * @param  list<class-string<SystemDashboard>>  $definitions
     */
    public function handle(string $panel, array $definitions): void
    {
        DB::transaction(function () use ($panel, $definitions): void {
            $keys = [];

            foreach ($definitions as $class) {
                $definition = new $class;
                $keys[] = $definition->key();

                $dashboard = Dashboard::query()->updateOrCreate(
                    ['panel' => $panel, 'system_key' => $definition->key()],
                    ['name' => $definition->name(), 'slug' => $definition->key()],
                );

                $dashboard->items()->delete();

                foreach ($definition->items() as $item) {
                    $dashboard->items()->create([
                        'widget' => $item['widget']::dashboardKey(),
                        'x' => $item['x'],
                        'y' => $item['y'],
                        'w' => $item['w'],
                        'h' => $item['h'],
                    ]);
                }
            }

            Dashboard::query()
                ->where('panel', $panel)
                ->whereNotNull('system_key')
                ->whereNotIn('system_key', $keys)
                ->delete();
        });
    }
}
