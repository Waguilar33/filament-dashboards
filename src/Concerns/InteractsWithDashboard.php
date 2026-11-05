<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Concerns;

use Illuminate\Support\Str;

trait InteractsWithDashboard
{
    public static function dashboardKey(): string
    {
        return Str::kebab(class_basename(static::class));
    }

    public static function dashboardLabel(): string
    {
        return Str::headline(Str::beforeLast(class_basename(static::class), 'Widget'));
    }

    public static function dashboardDescription(): ?string
    {
        return null;
    }

    public static function dashboardPreview(): ?string
    {
        return null;
    }

    /**
     * @return array{w: int, h: int}
     */
    public static function dashboardSize(): array
    {
        return ['w' => 6, 'h' => 4];
    }
}
