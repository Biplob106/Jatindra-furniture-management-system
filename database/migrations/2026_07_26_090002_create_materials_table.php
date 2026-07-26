<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Timber, board, hardware and finish. What a purchase brings in and an
     * order consumes.
     *
     * current_stock and avg_cost are denormalised on purpose: docs/schema.md
     * defines them as maintained from material_movements, which stays the
     * event log. Quantities are DECIMAL(12,3) because timber is bought in cft
     * to three places.
     */
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('category', ['wood', 'board', 'hardware', 'paint', 'polish', 'glue', 'other']);
            $table->enum('unit', ['cft', 'sqft', 'piece', 'kg', 'litre', 'bundle', 'set']);
            $table->decimal('current_stock', 12, 3)->default(0);
            $table->decimal('avg_cost', 12, 2)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
