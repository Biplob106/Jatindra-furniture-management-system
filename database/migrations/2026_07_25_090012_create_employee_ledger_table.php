<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single source of truth for what each worker has earned and taken.
     *
     * balance = SUM(credit) - SUM(debit), positive means the shop owes the
     * worker. There is deliberately no running balance column, and rows are
     * never deleted: a mistake is corrected with an `adjustment` entry.
     */
    public function up(): void
    {
        Schema::create('employee_ledger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('entry_date');
            $table->enum('type', [
                'opening', 'wage_earned', 'piece_earned', 'overtime', 'bonus',
                'advance', 'tiffin', 'payout', 'fine', 'adjustment',
            ]);
            $table->enum('direction', ['credit', 'debit']);
            $table->decimal('amount', 12, 2);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->enum('payment_method', ['cash', 'bkash', 'nagad', 'bank'])->nullable();
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees');
            $table->index(['employee_id', 'entry_date'], 'idx_emp_date');
            $table->index(['type', 'entry_date'], 'idx_type_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_ledger');
    }
};
