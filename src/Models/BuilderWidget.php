<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Waguilar\FilamentDashboards\Builders\BuilderWidgetBuilder;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\Models\Concerns\HasOwnerAndShares;

/**
 * @property int $id
 * @property string $panel
 * @property string|null $tenant_type
 * @property int|string|null $tenant_id
 * @property string|null $owner_type
 * @property int|string|null $owner_id
 * @property string|null $system_key
 * @property string $name
 * @property BuilderWidgetType $type
 * @property array<string, mixed> $config
 * @property int|null $refresh_interval
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseEloquentBuilder(BuilderWidgetBuilder::class)]
class BuilderWidget extends Model
{
    use HasOwnerAndShares;

    public const KEY_PREFIX = 'custom:';

    protected $guarded = [];

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.widgets');
    }

    public function dashboardKey(): string
    {
        return self::KEY_PREFIX.$this->id;
    }

    /**
     * @return list<string>
     */
    public function sourceKeys(): array
    {
        $parts = is_array($this->config['cards'] ?? null) ? $this->config['cards'] : [$this->config];

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $part): mixed => is_array($part) ? ($part['source'] ?? null) : null, $parts),
            is_string(...),
        )));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BuilderWidgetType::class,
            'config' => 'array',
            'refresh_interval' => 'integer',
        ];
    }
}
