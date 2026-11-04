<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('number');
            $table->string('customer');
            $table->string('status');
            $table->string('channel');
            $table->decimal('total', 10, 2);
            $table->unsignedInteger('items_count');
            $table->boolean('paid')->default(false);
            $table->dateTime('ordered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_orders');
    }
};
