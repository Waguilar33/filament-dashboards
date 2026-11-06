<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
use Waguilar\FilamentDashboards\Models\BuilderWidget;
use Waguilar\FilamentDashboards\Resources\BuilderWidgets\Schemas\BuilderWidgetForm;

class SaveBuilderWidget
{
    public function __construct(
        protected RecordWidgetVersion $recordWidgetVersion,
    ) {}

    /**
     * @param  array<mixed>  $data
     */
    public function handle(BuilderWidget $widget, BuilderWidgetType $type, array $data, ?Model $changedBy = null): BuilderWidget
    {
        $interval = $data['refresh_interval'] ?? null;

        if ($widget->exists && $widget->versions()->doesntExist()) {
            $this->recordWidgetVersion->handle($widget, null);
        }

        $widget->fill([
            'name' => Arr::string($data, 'name'),
            'type' => $type,
            'config' => BuilderWidgetForm::config($data),
            'refresh_interval' => is_numeric($interval) && ($minimum = FilamentDashboardsPlugin::get()->getMinRefreshInterval()) !== null ? max($minimum, (int) $interval) : null,
        ]);

        $changed = ! $widget->exists || $widget->isDirty(['name', 'config', 'refresh_interval']);
        $widget->save();

        if ($changed) {
            $this->recordWidgetVersion->handle($widget, $changedBy);
        }

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
