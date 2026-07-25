<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Counters behind the printable document numbers: SH-2607-0142 and its
     * siblings for sales, purchases and CNC jobs.
     *
     * One row per prefix per month. The unique key is what makes issuing safe
     * under concurrency: the row is created with insertOrIgnore and then locked
     * for update, so two clerks confirming an order at the same moment cannot
     * both walk away with 0142.
     */
    public function up(): void
    {
        Schema::create('number_series', function (Blueprint $table) {
            $table->id();
            $table->string('prefix', 10);          // SH, INV, PO, CNC
            $table->char('period', 4);             // 2607
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['prefix', 'period'], 'uk_series');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('number_series');
    }
};
