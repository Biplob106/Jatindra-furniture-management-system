<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('monthly_rent', 12, 2)->default(0);
            $table->unsignedTinyInteger('rent_due_day')->nullable();
            $table->string('landlord_name', 150)->nullable();
            $table->string('landlord_phone', 20)->nullable();
            $table->string('electricity_meter_no', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
