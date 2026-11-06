<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dashboard_id
 * @property string $widget
 * @property int|null $builder_widget_id
 * @property bool $shares_widget
 * @property bool $fits_content
 * @property int $x
 * @property int $y
 * @property int $w
 * @property int $h
 * @property array<string, mixed>|null $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DashboardItem extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::saving(function (self $item): void {
            $item->builder_widget_id = str_starts_with($item->widget, BuilderWidget::KEY_PREFIX)
                ? (int) substr($item->widget, strlen(BuilderWidget::KEY_PREFIX))
                : null;
        });
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

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.items');
    }

    /**
     * @return array<array-key, int>
     */
    public function cardWidths(): array
    {
        $widths = $this->options['card_widths'] ?? [];

        return is_array($widths) ? array_filter($widths, is_int(...)) : [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'w' => 'integer',
            'h' => 'integer',
            'options' => 'array',
            'shares_widget' => 'boolean',
            'fits_content' => 'boolean',
        ];
    }
}
