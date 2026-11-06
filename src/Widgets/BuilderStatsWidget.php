<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Widgets;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Reactive;
use Waguilar\FilamentDashboards\Actions\EvaluateStatsCards;
use Waguilar\FilamentDashboards\Actions\ResolveDateRange;
use Waguilar\FilamentDashboards\Widgets\Concerns\DrillsDownToRecords;
use Waguilar\FilamentDashboards\Widgets\Concerns\InteractsWithBuilderWidget;
use Waguilar\FilamentDashboards\Widgets\Stats\DashboardStat;
use Waguilar\FilamentDashboards\Widgets\Stats\StatCardResult;

class BuilderStatsWidget extends StatsOverviewWidget implements HasActions
{
    use DrillsDownToRecords;
    use InteractsWithActions;
    use InteractsWithBuilderWidget;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament-dashboards::stats-widget';

    public const COLUMNS = 12;

    /**
     * @var array<array-key, int>
     */
    #[Reactive]
    public array $cardWidths = [];

    #[Locked]
    public bool $resizableCards = false;

    /**
     * @return array<string, ?int>
     */
    protected function getColumns(): array
    {
        return ['@sm' => self::COLUMNS, '!@md' => self::COLUMNS];
    }

    public function getSectionContentComponent(): Component
    {
        $section = parent::getSectionContentComponent();

        if (! $section instanceof Section || ! (bool) ($this->widgetConfig()['show_title'] ?? false)) {
            return $section;
        }

        return $section
            ->heading($this->builderWidget()->name ?? $this->previewName)
            ->contained();
    }

    /**
     * @return array<DashboardStat>
     */
    protected function getStats(): array
    {
        $stats = array_map(function (StatCardResult $result): DashboardStat {
            if ($result->needsAttention) {
                $this->needsAttention = true;
            }

            return DashboardStat::make($result->label, $result->formatted)
                ->translateLabel(false)
                ->description($result->description)
                ->descriptionIcon($result->icon)
                ->color($result->color)
                ->cardKey($result->key)
                ->resizable($this->resizableCards)
                ->drillsDown(! $this->resizableCards && ! $result->needsAttention && $result->source !== null && $this->drillDownFields($result->source) !== []);
        }, app(EvaluateStatsCards::class)->handle(
            is_array($cards = $this->widgetConfig()['cards'] ?? null) ? $cards : [],
            $this->viewer(),
            $this->tenant(),
            app(ResolveDateRange::class)->handle($this->pageFilters),
        ));

        $defaultSpan = intdiv(self::COLUMNS, count($stats) >= 3 && count($stats) % 3 === 1 ? 4 : 3);

        foreach ($stats as $stat) {
            $span = $this->cardWidths[$stat->getCardKey()] ?? null;
            $stat->columnSpan($span === null
                ? ['@sm' => self::COLUMNS, '!@md' => self::COLUMNS, '@xl' => $defaultSpan, '!@lg' => $defaultSpan]
                : ['@sm' => $span, '!@md' => $span]);
        }

        return $stats;
    }

    protected function drillDownTarget(array $arguments): ?array
    {
        $cards = $this->widgetConfig()['cards'] ?? [];

        foreach (is_array($cards) ? array_values($cards) : [] as $index => $card) {
            if (is_array($card) && ($this->configString($card, 'key') ?? (string) $index) === ($arguments['card'] ?? null)) {
                return ['part' => $card, 'rule' => null, 'title' => $this->configString($card, 'label') ?? $this->source($card['source'] ?? null)?->label() ?? ''];
            }
        }

        return null;
    }
}
