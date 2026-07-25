<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 20)->unique();
            $table->string('alt_phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('area', 100)->nullable();
            $table->enum('customer_type', ['retail', 'dealer', 'contractor'])->default('retail');
            $table->decimal('opening_due', 12, 2)->default(0);
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name', 'idx_name');
            $table->index('area', 'idx_area');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
