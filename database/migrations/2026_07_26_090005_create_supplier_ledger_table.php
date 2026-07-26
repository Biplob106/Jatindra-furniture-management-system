<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single source of truth for what we owe each supplier.
     *
     * balance = SUM(credit) - SUM(debit), positive means we owe them. A
     * purchase is a credit; paying it down is a debit. Mirror image of
     * employee_ledger, and inverting it is silently wrong money.
     *
     * No running balance column, and rows are never deleted: a mistake is
     * corrected with an `adjustment` entry.
     */
    public function up(): void
    {
        Schema::create('supplier_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_id');
            $table->date('entry_date');
            $table->enum('type', ['opening', 'purchase', 'payment', 'return', 'discount', 'adjustment']);
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->index(['supplier_id', 'entry_date'], 'idx_sup_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger');
    }
};
