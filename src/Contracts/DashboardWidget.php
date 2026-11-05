<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Contracts;

interface DashboardWidget
{
    public static function dashboardKey(): string;

    public static function dashboardLabel(): string;

    public static function dashboardDescription(): ?string;

    public static function dashboardPreview(): ?string;

    /**
     * @return array{w: int, h: int}
     */
    public static function dashboardSize(): array;
}
