<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One payment handed over or taken in, which may settle several invoices.
     *
     * Money always moves here, so every row has a matching transactions row
     * written by CashService and an account it came out of or went into.
     * direction `out` is us paying a supplier, `in` is a customer paying us.
     */
    public function up(): void
    {
        Schema::create('party_payments', function (Blueprint $table) {
            $table->id();
            $table->enum('party_type', ['supplier', 'customer']);
            $table->unsignedBigInteger('party_id');
            $table->enum('direction', ['in', 'out']);
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->unsignedBigInteger('account_id');
            $table->enum('payment_method', ['cash', 'bkash', 'nagad', 'bank', 'cheque'])->default('cash');
            $table->string('reference_no', 50)->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts');
            $table->index(['party_type', 'party_id', 'payment_date'], 'idx_party');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_payments');
    }
};
