<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class SaveDashboardLayout
{
    public const COLUMNS = 12;

    public const MAX_HEIGHT = 24;

    /**
     * @param  array<mixed>  $layout
     */
    public function handle(Dashboard $dashboard, array $layout): void
    {
        DB::transaction(function () use ($dashboard, $layout): void {
            $items = $dashboard->items()->get()->keyBy('id');

            foreach ($layout as $position) {
                if (! is_array($position) || ! is_int($position['id'] ?? null)) {
                    continue;
                }

                $item = $items->get($position['id']);

                if (! $item instanceof DashboardItem) {
                    continue;
                }

                $w = $this->clamp($position['w'] ?? $item->w, 1, self::COLUMNS);

                $item->update([
                    'x' => $this->clamp($position['x'] ?? $item->x, 0, self::COLUMNS - $w),
                    'y' => $this->clamp($position['y'] ?? $item->y, 0, PHP_INT_MAX),
                    'w' => $w,
                    'h' => $this->clamp($position['h'] ?? $item->h, 1, self::MAX_HEIGHT),
                ]);
            }
        });
    }

    protected function clamp(mixed $value, int $min, int $max): int
    {
        return is_int($value) ? max($min, min($max, $value)) : $min;
    }
}
