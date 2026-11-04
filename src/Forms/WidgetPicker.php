<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Forms;

use Closure;
use Filament\Forms\Components\Field;

class WidgetPicker extends Field
{
    protected string $view = 'filament-dashboards::widget-picker';

    /**
     * @var list<array<string, mixed>>|Closure
     */
    protected array|Closure $sections = [];

    /**
     * @param  list<array<string, mixed>>|Closure  $sections
     */
    public function sections(array|Closure $sections): static
    {
        $this->sections = $sections;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getSections(): array
    {
        /** @var list<array<string, mixed>> */
        return $this->evaluate($this->sections);
    }

    /**
     * @return list<string>
     */
    public function getOptionValues(): array
    {
        $values = [];

        foreach ($this->getSections() as $section) {
            foreach (is_array($section['options'] ?? null) ? array_keys($section['options']) : [] as $value) {
                $values[] = (string) $value;
            }
        }

        return $values;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn (WidgetPicker $component): string => 'in:'.implode(',', $component->getOptionValues()));
    }
}
