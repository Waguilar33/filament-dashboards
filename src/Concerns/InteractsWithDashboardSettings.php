<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Concerns;

use Livewire\Attributes\Locked;

trait InteractsWithDashboardSettings
{
    /**
     * @var array<string, mixed>
     */
    #[Locked]
    public array $dashboardSettings = [];

    public function dashboardSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->dashboardSettings, $key, $default);
    }
}
