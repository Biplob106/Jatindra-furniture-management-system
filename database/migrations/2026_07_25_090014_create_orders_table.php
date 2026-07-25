<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // SH-2607-0142. Printable: it gets written on the paper slip
            // during the transition period, so it has to stay short.
            //
            // Nullable, which docs/schema.md originally was not. A draft has no
            // number: it earns one when it is confirmed, so an abandoned draft
            // does not burn one and leave a hole in the printed sequence. The
            // UNIQUE index still holds, and MySQL allows repeated NULLs under
            // one, so no two real orders can share a number.
            $table->string('order_no', 30)->nullable()->unique();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('shop_id');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->enum('status', ['draft', 'confirmed', 'in_production', 'ready', 'delivered', 'cancelled'])->default('draft');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            // paid_amount and due_amount are denormalised on purpose: the
            // schema calls them out as recalculated when a payment lands.
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->text('delivery_address')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('shop_id')->references('id')->on('shops');
            $table->index(['status', 'order_date'], 'idx_status_date');
            $table->index('expected_delivery_date', 'idx_delivery');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
