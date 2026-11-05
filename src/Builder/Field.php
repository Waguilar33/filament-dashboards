<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Builder;

use Closure;
use Waguilar\FilamentDashboards\Builder\Enums\FieldType;

final readonly class Field
{
    /**
     * @param  array<string, string>  $options
     * @param  (Closure(int|float): string)|null  $formatter
     */
    public function __construct(
        public string $name,
        public string $label,
        public FieldType $type,
        public array $options = [],
        public int|float $divisor = 1,
        public ?Closure $formatter = null,
    ) {}

    public static function number(string $name, string $label): self
    {
        return new self($name, $label, FieldType::Number);
    }

    public static function text(string $name, string $label): self
    {
        return new self($name, $label, FieldType::Text);
    }

    public static function date(string $name, string $label): self
    {
        return new self($name, $label, FieldType::Date);
    }

    public static function boolean(string $name, string $label): self
    {
        return new self($name, $label, FieldType::Boolean);
    }

    /**
     * @param  array<string, string>  $options
     */
    public static function choice(string $name, string $label, array $options): self
    {
        return new self($name, $label, FieldType::Choice, $options);
    }

    public function divideBy(int|float $divisor): self
    {
        return new self($this->name, $this->label, $this->type, $this->options, $divisor, $this->formatter);
    }

    /**
     * @param  Closure(int|float): string  $formatter
     */
    public function formatUsing(Closure $formatter): self
    {
        return new self($this->name, $this->label, $this->type, $this->options, $this->divisor, $formatter);
    }

    public function alias(): string
    {
        return str_replace('.', '__', $this->name);
    }

    public function format(int|float $value): ?string
    {
        return $this->formatter === null ? null : ($this->formatter)($value);
    }

    public function fromStored(int|float $value): int|float
    {
        return $this->divisor == 1 ? $value : $value / $this->divisor;
    }

    public function toStored(int|float $value): int|float
    {
        return $this->divisor == 1 ? $value : round($value * $this->divisor, 10);
    }

    public function valueLabel(mixed $value): string
    {
        if ($this->type === FieldType::Boolean) {
            return trans('filament-dashboards::builder.'.(filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no'));
        }

        $text = is_scalar($value) ? (string) $value : '';

        return $this->type === FieldType::Choice ? ($this->options[$text] ?? $text) : $text;
    }
}
