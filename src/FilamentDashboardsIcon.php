<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards;

use BackedEnum;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

final class FilamentDashboardsIcon
{
    public const NAVIGATION_DASHBOARDS = 'filament-dashboards::navigation.dashboards';

    public const NAVIGATION_WIDGETS = 'filament-dashboards::navigation.widgets';

    public const EDIT = 'filament-dashboards::actions.edit';

    public const DUPLICATE = 'filament-dashboards::actions.duplicate';

    public const SHARE = 'filament-dashboards::actions.share';

    public const DELETE = 'filament-dashboards::actions.delete';

    public const REMOVE = 'filament-dashboards::actions.remove';

    public const PRINT = 'filament-dashboards::actions.print';

    public const EMAIL = 'filament-dashboards::actions.email';

    public const ALERTS = 'filament-dashboards::actions.alerts';

    public const HISTORY = 'filament-dashboards::actions.history';

    public const HOME = 'filament-dashboards::actions.home';

    public const SETTINGS = 'filament-dashboards::actions.settings';

    public const EXPORT = 'filament-dashboards::actions.export';

    public const TRANSFER = 'filament-dashboards::actions.transfer';

    public const FIT_HEIGHT = 'filament-dashboards::actions.fit-height';

    public const EMPTY = 'filament-dashboards::empty';

    public const INCREASE = 'filament-dashboards::stats.increase';

    public const DECREASE = 'filament-dashboards::stats.decrease';

    public const PICKER_PREVIOUS = 'filament-dashboards::picker.previous';

    public const PICKER_NEXT = 'filament-dashboards::picker.next';

    public const PICKER_SELECTED = 'filament-dashboards::picker.selected';

    private const DEFAULTS = [
        self::NAVIGATION_DASHBOARDS => Heroicon::OutlinedSquares2x2,
        self::NAVIGATION_WIDGETS => Heroicon::OutlinedChartPie,
        self::EDIT => Heroicon::OutlinedPencilSquare,
        self::DUPLICATE => Heroicon::OutlinedDocumentDuplicate,
        self::SHARE => Heroicon::OutlinedShare,
        self::DELETE => Heroicon::OutlinedTrash,
        self::REMOVE => Heroicon::OutlinedTrash,
        self::PRINT => Heroicon::OutlinedPrinter,
        self::EMAIL => Heroicon::OutlinedEnvelope,
        self::ALERTS => Heroicon::OutlinedBellAlert,
        self::HISTORY => Heroicon::OutlinedClock,
        self::HOME => Heroicon::OutlinedHome,
        self::SETTINGS => Heroicon::OutlinedCog6Tooth,
        self::EXPORT => Heroicon::OutlinedArrowDownTray,
        self::TRANSFER => Heroicon::OutlinedArrowsRightLeft,
        self::FIT_HEIGHT => Heroicon::OutlinedArrowsUpDown,
        self::EMPTY => Heroicon::OutlinedSquares2x2,
        self::INCREASE => Heroicon::ArrowTrendingUp,
        self::DECREASE => Heroicon::ArrowTrendingDown,
        self::PICKER_PREVIOUS => Heroicon::ChevronLeft,
        self::PICKER_NEXT => Heroicon::ChevronRight,
        self::PICKER_SELECTED => Heroicon::CheckCircle,
    ];

    public static function get(string $alias): string|BackedEnum|Htmlable
    {
        return FilamentIcon::resolve($alias) ?? self::DEFAULTS[$alias];
    }
}
