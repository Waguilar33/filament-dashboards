<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Forms;

use Closure;
use Filament\Forms\Components\Field;

class WidgetPicker extends Field
{
    protected string $view = 'filament-dashboards::widget-picker';

    /**
     * @var array<array{heading: string, description: string, carousel: bool, options: array<string, array{label: string, description: ?string, preview: ?string, kind: string}>}>|Closure
     */
    protected array|Closure $sections = [];

    /**
     * @param  array<array{heading: string, description: string, carousel: bool, options: array<string, array{label: string, description: ?string, preview: ?string, kind: string}>}>|Closure  $sections
     */
    public function sections(array|Closure $sections): static
    {
        $this->sections = $sections;

        return $this;
    }

    /**
     * @return array<array{heading: string, description: string, carousel: bool, options: array<string, array{label: string, description: ?string, preview: ?string, kind: string}>}>
     */
    public function getSections(): array
    {
        /** @var array<array{heading: string, description: string, carousel: bool, options: array<string, array{label: string, description: ?string, preview: ?string, kind: string}>}> */
        return $this->evaluate($this->sections);
    }

    /**
     * @return list<string>
     */
    public function getOptionValues(): array
    {
        $values = [];

        foreach ($this->getSections() as $section) {
            array_push($values, ...array_map(strval(...), array_keys($section['options'])));
        }

        return $values;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rule(fn (WidgetPicker $component): string => 'in:'.implode(',', $component->getOptionValues()));
    }
}
