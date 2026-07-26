<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The nightly reconciliation: what the books say the drawer should hold
     * against what was actually counted.
     *
     * One row per shop per day, which is what makes closing the same day twice
     * safe.
     */
    public function up(): void
    {
        Schema::create('daily_closings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id');
            $table->date('closing_date');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('total_in', 14, 2)->default(0);
            $table->decimal('total_out', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('expected_closing', 14, 2)->default(0);
            $table->decimal('counted_cash', 14, 2)->default(0);
            $table->decimal('difference', 14, 2)->default(0);
            $table->decimal('credit_purchase_today', 14, 2)->default(0);
            $table->decimal('total_payable', 14, 2)->default(0);
            $table->decimal('total_receivable', 14, 2)->default(0);
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->text('note')->nullable();

            $table->unique(['shop_id', 'closing_date'], 'uk_shop_date');
            $table->foreign('shop_id')->references('id')->on('shops');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_closings');
    }
};
