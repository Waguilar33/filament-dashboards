<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Components\Attributes\Unexposed;

trait HasBuilderHeaderActions
{
    /**
     * @var array<Action|ActionGroup>
     */
    protected array $cachedBuilderHeaderActions = [];

    /**
     * @return array<Action|ActionGroup>
     */
    abstract protected function builderHeaderActions(): array;

    #[Unexposed]
    public function cacheHasBuilderHeaderActions(): void
    {
        foreach ($this->builderHeaderActions() as $action) {
            if ($action instanceof ActionGroup) {
                $action->livewire($this);

                /** @var array<string, Action> $flatActions */
                $flatActions = $action->getFlatActions();

                $this->mergeCachedActions($flatActions);
            } else {
                $this->cacheAction($action);
            }

            $this->cachedBuilderHeaderActions[] = $action;
        }
    }

    /**
     * @return array<Action|ActionGroup>
     */
    protected function getVisibleBuilderHeaderActions(): array
    {
        return array_values(array_filter($this->cachedBuilderHeaderActions, fn (Action|ActionGroup $action): bool => $action->isVisible()));
    }
}
