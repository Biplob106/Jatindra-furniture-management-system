<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Readymade furniture: what is finished and standing on the floor, as
     * opposed to an order still being built.
     *
     * current_stock is denormalised the same way materials.current_stock is,
     * maintained from stock_movements, which stays the event log. Two decimal
     * places rather than three: nobody sells a third of a chair.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 50)->unique();
            $table->string('name', 200);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->text('description')->nullable();
            $table->string('wood_type', 100)->nullable();
            $table->string('size_label', 100)->nullable();
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('current_stock', 10, 2)->default(0);
            $table->decimal('min_stock', 10, 2)->default(0);
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('category_id')->references('id')->on('product_categories');
            $table->foreign('shop_id')->references('id')->on('shops');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
