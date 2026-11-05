<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Waguilar\FilamentDashboards\Builders\DashboardShareBuilder;
use Waguilar\FilamentDashboards\Enums\ShareLevel;

/**
 * @property int $id
 * @property string $subject_type
 * @property int|string $subject_id
 * @property string $shareable_type
 * @property int|string $shareable_id
 * @property ShareLevel $level
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseEloquentBuilder(DashboardShareBuilder::class)]
class DashboardShare extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.shares');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => ShareLevel::class,
        ];
    }
}
