<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which invoices a payment paid down, and by how much.
     *
     * This is what lets one 50,000 taka handover clear three challans and
     * leave the fourth half open. The allocations of a payment sum to its
     * amount; anything left unallocated is an on-account balance the supplier
     * ledger still carries.
     *
     * allocatable_type is a class name (Purchase, Order, Sale, CncJob), so no
     * foreign key on the target.
     */
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('party_payment_id');
            $table->string('allocatable_type', 100);
            $table->unsignedBigInteger('allocatable_id');
            $table->decimal('allocated_amount', 12, 2);

            $table->foreign('party_payment_id')->references('id')->on('party_payments')->cascadeOnDelete();
            $table->index(['allocatable_type', 'allocatable_id'], 'idx_alloc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
