<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class MoveDashboardItem
{
    public function handle(Dashboard $dashboard, int $itemId, int $offset): void
    {
        DB::transaction(function () use ($dashboard, $itemId, $offset): void {
            $items = $dashboard->items()->orderBy('y')->orderBy('x')->orderBy('id')->get()->all();
            $from = array_search($itemId, array_map(fn (DashboardItem $item): int => $item->id, $items), true);

            if ($from === false || ! isset($items[$from + $offset])) {
                return;
            }

            $moved = array_splice($items, $from, 1);
            array_splice($items, $from + $offset, 0, $moved);

            foreach ($items as $position => $item) {
                $item->update(['x' => 0, 'y' => $position]);
            }
        });
    }
}
