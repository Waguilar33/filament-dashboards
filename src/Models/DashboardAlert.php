<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Waguilar\FilamentDashboards\Enums\AlertOperator;

/**
 * @property int $id
 * @property string $panel
 * @property string|null $tenant_type
 * @property int|string|null $tenant_id
 * @property int $dashboard_id
 * @property int $builder_widget_id
 * @property string $card_key
 * @property string $user_type
 * @property int|string $user_id
 * @property AlertOperator $operator
 * @property float $threshold
 * @property bool $triggered
 * @property string|null $last_period
 */
class DashboardAlert extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.alerts', 'custom_dashboard_alerts');
    }

    /**
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
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
    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function tenant(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'operator' => AlertOperator::class,
            'threshold' => 'float',
            'triggered' => 'boolean',
        ];
    }
}
