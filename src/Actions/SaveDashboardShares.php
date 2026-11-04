<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\Dashboard;

class SaveDashboardShares
{
    /**
     * @param  list<array{shareable_type: string, shareable_id: int|string, level: ShareLevel|string}>  $shares
     */
    public function handle(Dashboard $dashboard, array $shares): void
    {
        DB::transaction(function () use ($dashboard, $shares): void {
            $dashboard->shares()->delete();

            foreach ($shares as $share) {
                if ($share['shareable_type'] === $dashboard->owner_type && (string) $share['shareable_id'] === (string) $dashboard->owner_id) {
                    continue;
                }

                $dashboard->shares()->updateOrCreate(
                    ['shareable_type' => $share['shareable_type'], 'shareable_id' => $share['shareable_id']],
                    ['level' => $share['level']],
                );
            }
        });
    }
}
