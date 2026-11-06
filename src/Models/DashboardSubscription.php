<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Waguilar\FilamentDashboards\Enums\DigestFrequency;

/**
 * @property int $id
 * @property string $panel
 * @property string|null $tenant_type
 * @property int|string|null $tenant_id
 * @property int $dashboard_id
 * @property string $user_type
 * @property int|string $user_id
 * @property DigestFrequency $frequency
 * @property Carbon|null $last_sent_at
 */
class DashboardSubscription extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        /** @var string */
        return config('filament-dashboards.tables.subscriptions', 'custom_dashboard_subscriptions');
    }

    /**
     * @return BelongsTo<Dashboard, $this>
     */
    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
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
            'frequency' => DigestFrequency::class,
            'last_sent_at' => 'datetime',
        ];
    }
}
