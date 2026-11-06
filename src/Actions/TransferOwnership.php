<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Waguilar\FilamentDashboards\Enums\ShareLevel;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\Dashboard;
use Waguilar\FilamentDashboards\Models\DashboardItem;

class TransferOwnership
{
    public function handle(Dashboard|BuilderWidget $record, Model $newOwner): void
    {
        DB::transaction(function () use ($record, $newOwner): void {
            $previous = $record->owner;

            $record->shares()->whereMorphedTo('shareable', $newOwner)->delete();

            if ($previous instanceof Model && ! $previous->is($newOwner)) {
                $record->shares()->updateOrCreate(
                    ['shareable_type' => $previous->getMorphClass(), 'shareable_id' => $previous->getKey()],
                    ['level' => ShareLevel::Edit],
                );
            }

            $record->owner()->associate($newOwner)->save();

            if ($record instanceof BuilderWidget) {
                $record->placements()->with('dashboard')->get()->each(fn (DashboardItem $item): bool => $item->update([
                    'shares_widget' => $item->dashboard instanceof Dashboard && $item->dashboard->isOwnedBy($newOwner),
                ]));

                return;
            }

            $record->items()
                ->whereIn('builder_widget_id', BuilderWidget::query()->whereMorphedTo('owner', $newOwner)->select('id'))
                ->update(['shares_widget' => true]);
        });
    }
}
