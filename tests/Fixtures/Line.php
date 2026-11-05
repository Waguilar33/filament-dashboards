<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class Line extends Model
{
    protected $table = 'fixture_lines';

    protected $guarded = [];

    public $timestamps = false;

    public static function createTables(): void
    {
        Schema::create('fixture_families', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('fixture_products', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->foreignId('family_id')->nullable();
        });

        Schema::create('fixture_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id');
            $table->string('shop');
            $table->decimal('amount', 10, 2);
            $table->dateTime('sold_at');
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
