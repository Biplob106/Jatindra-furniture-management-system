<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rates differ worker to worker, so a trade carries no rate. The rate
     * lives only on employees.daily_rate.
     */
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn('default_daily_rate');
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->decimal('default_daily_rate', 10, 2)->default(0)->after('name');
        });
    }
};
