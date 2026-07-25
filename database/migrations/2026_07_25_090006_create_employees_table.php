<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 20)->unique();
            $table->string('name', 150);
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('photo', 255)->nullable();
            $table->string('nid_no', 30)->nullable();
            $table->unsignedBigInteger('trade_id')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->enum('wage_type', ['daily', 'monthly', 'piece']);
            $table->decimal('daily_rate', 10, 2)->default(0);
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->date('joining_date')->nullable();
            $table->string('guarantor_name', 150)->nullable();
            $table->string('guarantor_phone', 20)->nullable();
            $table->decimal('opening_advance', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('trade_id')->references('id')->on('trades');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
