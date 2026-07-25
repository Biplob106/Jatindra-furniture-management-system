<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * THE CASH LEDGER. One row for every actual movement of money.
     *
     * A row here means physical money moved. A credit purchase writes to
     * purchases and supplier_ledger and nothing here; that omission is the
     * whole point of the design. Only CashService writes this table.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->date('txn_date');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->enum('direction', ['in', 'out']);
            $table->decimal('amount', 12, 2);
            $table->enum('source_type', [
                'order_payment', 'sale', 'cnc_payment', 'purchase_payment',
                'expense', 'employee_payment', 'capital_in', 'owner_draw',
                'transfer_in', 'transfer_out', 'adjustment',
            ]);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('party_type', 50)->nullable();
            $table->unsignedBigInteger('party_id')->nullable();
            $table->enum('payment_method', ['cash', 'bkash', 'nagad', 'bank', 'cheque'])->default('cash');
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->index(['txn_date', 'shop_id'], 'idx_date_shop');
            $table->index(['source_type', 'source_id'], 'idx_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
