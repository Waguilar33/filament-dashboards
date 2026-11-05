<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Actions;

use Illuminate\Database\Eloquent\Model;
use Waguilar\FilamentDashboards\Models\BuilderWidget;

class CloneBuilderWidget
{
    public function handle(BuilderWidget $source, Model $owner, ?Model $tenant, string $name): BuilderWidget
    {
        $clone = new BuilderWidget([
            'panel' => $source->panel,
            'name' => $name,
            'type' => $source->type,
            'config' => $source->config,
            'refresh_interval' => $source->refresh_interval,
        ]);
        $clone->owner()->associate($owner);
        $clone->tenant()->associate($tenant);
        $clone->save();

        return $clone;
    }
}
