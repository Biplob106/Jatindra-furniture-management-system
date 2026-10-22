<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Night shift support.
 *
 * Attendance gains three night statuses, the employee ledger gains a dedicated
 * `night_allowance` credit type so night pay is never folded into the day wage,
 * and employees gain an optional flat night rate that overrides the
 * half-of-daily-wage default.
 *
 * Enum columns are altered with explicit MODIFY statements so the change is
 * reversible and the exact value list stays visible here.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE attendance MODIFY COLUMN status ".
            "ENUM('present','absent','half_day','leave','holiday','night_only','full_day_night','half_day_night') ".
            "NOT NULL DEFAULT 'present'"
        );

        DB::statement(
            "ALTER TABLE employee_ledger MODIFY COLUMN type ".
            "ENUM('opening','wage_earned','piece_earned','overtime','bonus',".
            "'advance','tiffin','payout','fine','adjustment','night_allowance') NOT NULL"
        );

        Schema::table('employees', function (Blueprint $table) {
            // Flat per-employee night allowance. Null means fall back to the
            // configured fraction of the daily wage.
            $table->decimal('night_rate', 10, 2)->nullable()->after('daily_rate');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('night_rate');
        });

        DB::statement(
            "ALTER TABLE employee_ledger MODIFY COLUMN type ".
            "ENUM('opening','wage_earned','piece_earned','overtime','bonus',".
            "'advance','tiffin','payout','fine','adjustment') NOT NULL"
        );

        DB::statement(
            "ALTER TABLE attendance MODIFY COLUMN status ".
            "ENUM('present','absent','half_day','leave','holiday') NOT NULL DEFAULT 'present'"
        );
    }
};
