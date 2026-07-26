<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A supplier challan, priced.
     *
     * A credit purchase writes this row and a supplier_ledger credit and
     * nothing else. No transactions row: no money moved. That is the case the
     * whole three-ledger design exists to protect.
     *
     * paid_amount and due_amount are denormalised on purpose, recalculated
     * from payment_allocations when money lands, the same way orders work.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            // PO-2607-0031. Issued by NumberSeries, never from a form.
            $table->string('purchase_no', 30)->unique();
            $table->unsignedBigInteger('supplier_id');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->date('purchase_date');
            // The supplier's own challan number, so a paper slip can be found.
            $table->string('reference_no', 50)->nullable();
            $table->enum('payment_type', ['cash', 'credit', 'partial'])->default('cash');
            $table->date('payment_due_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('transport_cost', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'returned'])->default('pending');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->foreign('shop_id')->references('id')->on('shops');
            // The aging report reads this: what is due, and how late.
            $table->index(['payment_due_date', 'status'], 'idx_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
