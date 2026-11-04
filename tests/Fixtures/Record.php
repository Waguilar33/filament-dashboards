<?php

declare(strict_types=1);

namespace Waguilar\FilamentDashboards\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class Record extends Model
{
    protected $table = 'builder_records';

    protected $guarded = [];

    public $timestamps = false;

    public static function createTable(): void
    {
        Schema::create('builder_records', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('kind')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->decimal('other', 10, 2)->nullable();
            $table->dateTime('happened_at')->nullable();
        });
    }
}
