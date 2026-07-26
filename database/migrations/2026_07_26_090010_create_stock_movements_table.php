<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every time a finished product moves.
     *
     * Deliberately separate from material_movements: raw timber and a finished
     * almirah are counted in different units, costed differently, and move for
     * different reasons. One table with a nullable half of its columns would
     * serve neither well.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->date('movement_date');
            $table->enum('type', [
                'production_in', 'purchase_in', 'sale_out', 'order_out',
                'transfer_in', 'transfer_out', 'damage', 'adjustment',
            ]);
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->index(['product_id', 'movement_date'], 'idx_prod_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
