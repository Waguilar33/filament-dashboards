<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

class FindBuilderWidget
{
    public function handle(int $id, Model $user): ?BuilderWidget
    {
        return BuilderWidget::query()->whereKey($id)->visibleTo($user)->first();
    }
}
