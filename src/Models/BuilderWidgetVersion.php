<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $builder_widget_id
 * @property string $name
 * @property array<string, mixed> $config
 * @property int|null $refresh_interval
 * @property string|null $changed_by_type
 * @property int|string|null $changed_by_id
 * @property Carbon|null $created_at
 */
class BuilderWidgetVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.versions', 'custom_dashboard_widget_versions');
    }

    /**
     * @return BelongsTo<BuilderWidget, $this>
     */
    public function builderWidget(): BelongsTo
    {
        return $this->belongsTo(BuilderWidget::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function changedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'refresh_interval' => 'integer',
        ];
    }
}
