<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The lines on a challan.
     *
     * item_id points at materials or products depending on item_type, so it
     * carries no foreign key. products arrives in phase 6; until then only
     * `material` lines are written.
     */
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_id');
            $table->enum('item_type', ['material', 'product']);
            $table->unsignedBigInteger('item_id');
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 20)->nullable();
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('note', 255)->nullable();

            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
            $table->index(['item_type', 'item_id'], 'idx_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
