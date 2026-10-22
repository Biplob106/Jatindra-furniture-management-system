<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An archived, immutable snapshot of a worker's payroll for a finalized period.
 *
 * This does NOT replace the ledger: live balances are still computed from
 * employee_ledger as SUM(credit) - SUM(debit). A settlement row is the frozen
 * record of what a period was officially settled at, so a later ledger
 * correction cannot silently rewrite what was already finalized. A period is
 * finalized once; to change it, the row is reversed (kept for the record) and
 * the period finalized again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settlements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('earned', 12, 2);
            $table->decimal('night', 12, 2);
            $table->decimal('paid', 12, 2);
            $table->decimal('deductions', 12, 2);
            $table->decimal('net_balance', 14, 2);
            $table->timestamp('settled_at');
            $table->unsignedBigInteger('settled_by')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('reversed_by')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->foreign('employee_id')->references('id')->on('employees');
            $table->index(['employee_id', 'period_start', 'period_end'], 'idx_emp_period');
            $table->index(['period_start', 'period_end'], 'idx_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_settlements');
    }
};
