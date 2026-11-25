<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Renderers;

use Filament\Support\Enums\Alignment;

final readonly class TableColumn
{
    public function __construct(
        public string $key,
        public string $label,
        public ColumnType $type = ColumnType::Text,
        public Alignment $alignment = Alignment::Start,
    ) {}

    /**
     * @return array{key: string, label: string, type: string, alignment: string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'alignment' => $this->alignment->value,
        ];
    }
}
