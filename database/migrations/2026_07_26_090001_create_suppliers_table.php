<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who we buy from. Master data, so it soft-deletes: a supplier with
     * purchases behind them can be switched off but never erased.
     *
     * opening_due is what was owed on the day the shop moved off paper. It is
     * seeded as an `opening` supplier_ledger row, not read from here, so the
     * balance arithmetic has a single source.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('business_name', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            $table->enum('supplier_type', ['wood', 'hardware', 'paint', 'transport', 'other'])->default('other');
            $table->decimal('opening_due', 12, 2)->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);
            $table->smallInteger('default_credit_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
