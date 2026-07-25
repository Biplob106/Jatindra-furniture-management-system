<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->date('work_date');
            $table->enum('status', ['present', 'absent', 'half_day', 'leave', 'holiday'])->default('present');
            $table->time('in_time')->nullable();
            $table->time('out_time')->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('overtime_rate', 10, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->unsignedBigInteger('marked_by')->nullable();
            $table->timestamps();

            // One row per employee per day. This is what makes re-saving a
            // date an upsert rather than a second wage credit.
            $table->unique(['employee_id', 'work_date'], 'uk_emp_date');
            $table->foreign('employee_id')->references('id')->on('employees');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
