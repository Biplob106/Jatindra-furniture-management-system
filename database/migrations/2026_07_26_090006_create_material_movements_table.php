<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every time stock moves. Purchases bring it in, orders consume it,
     * offcuts are written off as wastage.
     *
     * order_id is what makes per-order material cost answerable later, so it
     * is filled whenever a consumption is against a job rather than general
     * stock.
     */
    public function up(): void
    {
        Schema::create('material_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('material_id');
            $table->date('movement_date');
            $table->enum('type', ['in', 'out', 'wastage', 'return', 'adjustment']);
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('material_id')->references('id')->on('materials');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->index(['material_id', 'movement_date'], 'idx_mat_date');
            $table->index('order_id', 'idx_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_movements');
    }
};
