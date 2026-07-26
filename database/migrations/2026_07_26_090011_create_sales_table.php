<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A counter sale of readymade stock.
     *
     * customer_id is nullable and customer_name sits beside it, because most
     * counter sales are to somebody who will never come back and does not want
     * to be entered into a customer list to buy one chair. A credit sale is
     * the exception: that one needs a real customer to owe the money.
     */
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            // INV-2607-0031. Issued by NumberSeries, never from a form.
            $table->string('invoice_no', 30)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->unsignedBigInteger('shop_id');
            $table->date('sale_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->index(['sale_date', 'shop_id'], 'idx_date_shop');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
