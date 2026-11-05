<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $dashboard_id
 * @property string $widget
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
        ];
    }
}
