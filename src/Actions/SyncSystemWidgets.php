<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\SystemDashboards\SystemWidget;

class SyncSystemWidgets
{
    /**
     * @param  list<class-string<SystemWidget>>  $definitions
     */
    public function handle(string $panel, array $definitions): void
    {
        DB::transaction(function () use ($panel, $definitions): void {
            $keys = [];

            foreach ($definitions as $class) {
                $definition = new $class;
                $keys[] = $definition->key();

                BuilderWidget::query()->updateOrCreate(
                    ['panel' => $panel, 'system_key' => $definition->key()],
                    [
                        'name' => $definition->name(),
                        'type' => $definition->type(),
                        'config' => $definition->config(),
                        'refresh_interval' => $definition->refreshInterval(),
                    ],
                );
            }

            BuilderWidget::query()
                ->where('panel', $panel)
                ->whereNotNull('system_key')
                ->whereNotIn('system_key', $keys)
                ->get()
                ->each(fn (BuilderWidget $widget): ?bool => $widget->delete());
        });
    }
}
