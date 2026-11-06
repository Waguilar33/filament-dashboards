<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Models\BuilderWidgetVersion;

class RecordWidgetVersion
{
    public function handle(BuilderWidget $widget, ?Model $changedBy): BuilderWidgetVersion
    {
        $version = new BuilderWidgetVersion([
            'builder_widget_id' => $widget->id,
            'name' => Arr::string($widget->getAttributes(), 'name'),
            'config' => $widget->config,
            'refresh_interval' => $widget->refresh_interval,
        ]);
        $version->changedBy()->associate($changedBy);
        $version->save();

        BuilderWidgetVersion::query()
            ->where('builder_widget_id', $widget->id)
            ->whereKeyNot(BuilderWidgetVersion::query()->where('builder_widget_id', $widget->id)->latest('id')->limit(BuilderWidgetVersion::KEEP)->pluck('id'))
            ->delete();

        return $version;
    }
}
