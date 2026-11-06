<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Waguilar\FilamentDashboards\Builders\BuilderWidgetBuilder;
use Waguilar\FilamentDashboards\Builders\DashboardBuilder;
use Waguilar\FilamentDashboards\Enums\BuilderWidgetType;
use Waguilar\FilamentDashboards\FilamentDashboardsPlugin;
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
    use HasOwnerAndShares {
        canBeEditedBy as canBeEditedByOwnerOrShare;
    }

    public const KEY_PREFIX = 'custom:';

    protected $guarded = [];

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.widgets');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $widget): void {
            $widget->placements()->delete();
            $widget->versions()->delete();
            DashboardAlert::query()->where('builder_widget_id', $widget->getKey())->delete();
        });
    }

    /**
     * @return HasMany<BuilderWidgetVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(BuilderWidgetVersion::class);
    }

    /**
     * @return HasMany<DashboardItem, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(DashboardItem::class);
    }

    public function canBeEditedBy(Model $user): bool
    {
        if ($this->isSystem()) {
            return false;
        }

        return $this->canBeEditedByOwnerOrShare($user)
            || $this->placements()
                ->where('shares_widget', true)
                ->whereHas('dashboard', fn (DashboardBuilder $dashboards): DashboardBuilder => $dashboards->editableBy($user))
                ->exists();
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
        $parts = $this->parts();

        return array_values(array_unique(array_filter(
            array_map(fn (mixed $part): mixed => is_array($part) ? ($part['source'] ?? null) : null, $parts),
            is_string(...),
        )));
    }

    public function followsDashboardDates(): bool
    {
        $parts = $this->parts();

        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }

            $period = $part['period'] ?? null;

            if (filled($part['dates_field'] ?? null) ? in_array($period, [null, 'dashboard'], true) : $period === 'dashboard') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    protected function parts(): array
    {
        $parts = match ($this->type) {
            BuilderWidgetType::Stats => $this->config['cards'] ?? null,
            BuilderWidgetType::Figures => $this->config['columns'] ?? null,
            default => [$this->config],
        };

        return is_array($parts) ? $parts : [];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (mixed $value, array $attributes): string => ($this->system_key === null ? null : FilamentDashboardsPlugin::forPanel($this->panel)?->getSystemWidgetName($this->system_key))
            ?? Arr::string($attributes, 'name'));
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
