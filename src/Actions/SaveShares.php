<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;

class SaveShares
{
    /**
     * @param  list<array{shareable_type: string, shareable_id: int|string, level: ShareLevel|string}>  $shares
     */
    public function handle(Dashboard|BuilderWidget $subject, array $shares): void
    {
        DB::transaction(function () use ($subject, $shares): void {
            $subject->shares()->delete();

            foreach ($shares as $share) {
                if ($share['shareable_type'] === $subject->owner_type && (string) $share['shareable_id'] === (string) $subject->owner_id) {
                    continue;
                }

                $subject->shares()->updateOrCreate(
                    ['shareable_type' => $share['shareable_type'], 'shareable_id' => $share['shareable_id']],
                    ['level' => $share['level']],
                );
            }
        });
    }
}
