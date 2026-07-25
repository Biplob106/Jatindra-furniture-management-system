<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who worked on which order item. This is where piece workers earn: an
     * order_item_work reaching `done` with an agreed_amount writes a
     * piece_earned credit to employee_ledger.
     *
     * Held back from phase 2 because it carries a foreign key to order_items,
     * which only exists now.
     */
    public function up(): void
    {
        Schema::create('order_item_works', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_item_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('trade_id')->nullable();
            $table->string('work_type', 100)->nullable();
            $table->decimal('agreed_amount', 12, 2)->default(0);
            $table->date('assigned_date')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->enum('status', ['assigned', 'working', 'done', 'rejected'])->default('assigned');
            $table->text('note')->nullable();

            $table->foreign('order_item_id')->references('id')->on('order_items')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_works');
    }
};
