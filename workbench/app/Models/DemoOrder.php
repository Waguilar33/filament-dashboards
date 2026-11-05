<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Workbench\Database\Factories\DemoOrderFactory;

/**
 * @property int $id
 * @property string $number
 * @property string $customer
 * @property string $status
 * @property string $channel
 * @property string $total
 * @property int $items_count
 * @property bool $paid
 * @property Carbon $ordered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class DemoOrder extends Model
{
    /** @use HasFactory<DemoOrderFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'items_count' => 'integer',
            'paid' => 'boolean',
            'ordered_at' => 'datetime',
        ];
    }
}
