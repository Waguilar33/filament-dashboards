<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;

class SaveBuilderWidget
{
    /**
     * @param  array<mixed>  $data
     */
    public function handle(BuilderWidget $widget, BuilderWidgetType $type, array $data): BuilderWidget
    {
        $interval = $data['refresh_interval'] ?? null;

        $widget->fill([
            'name' => Arr::string($data, 'name'),
            'type' => $type,
            'config' => BuilderWidgetForm::config($data),
            'refresh_interval' => is_numeric($interval) ? max(5, (int) $interval) : null,
        ])->save();

        return $widget;
    }

    public static function newFor(Model $owner, ?Model $tenant, string $panel): BuilderWidget
    {
        $widget = new BuilderWidget(['panel' => $panel]);
        $widget->owner()->associate($owner);
        $widget->tenant()->associate($tenant);

        return $widget;
    }
}
