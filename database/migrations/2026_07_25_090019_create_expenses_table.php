<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shop running costs: rent, current bill, tea, transport, repairs.
     *
     * Section 9 pairs every expense with a transactions row out. The expense
     * is the operational record of what the money was for; the transactions
     * row is the money actually leaving.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->date('expense_date');
            $table->decimal('amount', 12, 2);
            $table->string('paid_to', 150)->nullable();
            $table->enum('payment_method', ['cash', 'bkash', 'nagad', 'bank'])->default('cash');
            $table->unsignedBigInteger('account_id')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('expense_categories');
            $table->index(['expense_date', 'category_id'], 'idx_date_cat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
