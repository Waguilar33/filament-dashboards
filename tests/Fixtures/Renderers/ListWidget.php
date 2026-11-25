<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures\Renderers;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;
use Waguilar\FilamentDashboards\Renderers\TableColumn;
use Waguilar\FilamentDashboards\Renderers\TableRendererWidget;
use Waguilar\FilamentDashboards\Renderers\TableResult;
use Waguilar\FilamentDashboards\Renderers\TableRow;

final class ListWidget extends TableRendererWidget
{
    protected function body(TableResult $result): HtmlString
    {
        $item = fn (TableRow $row, string $class): string => sprintf(
            '<li class="%s"%s>%s</li>',
            $class,
            $row->drillsDown() ? ' wire:click="mountAction(\'drillDown\', '.e(Js::from($row->drillDown)).')"' : '',
            implode(' | ', array_map(fn (TableColumn $column): string => e($column->label.': '.$row->display($column->key)), $result->columns)),
        );

        return new HtmlString('<ul class="list-renderer">'
            .implode('', array_map(fn (TableRow $row): string => $item($row, 'list-row'), $result->rows))
            .($result->totals === null ? '' : $item($result->totals, 'list-total'))
            .'</ul>');
    }
}
