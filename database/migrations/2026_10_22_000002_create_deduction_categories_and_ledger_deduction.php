<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Custom deductions.
 *
 * Admins define any number of deduction categories (breakage, uniform, loan
 * instalment, ...). A deduction is a single ledger debit — the shop owes the
 * worker less — tagged with its category. It never moves cash, so it writes no
 * transactions row, exactly like a fine.
 *
 * Advances and tiffin already debit the ledger when handed over, so they are
 * NOT deduction categories: recovering them here would subtract the same money
 * twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement(
            "ALTER TABLE employee_ledger MODIFY COLUMN type ".
            "ENUM('opening','wage_earned','piece_earned','overtime','night_allowance','bonus',".
            "'advance','tiffin','payout','fine','deduction','adjustment') NOT NULL"
        );

        Schema::table('employee_ledger', function (Blueprint $table) {
            $table->unsignedBigInteger('deduction_category_id')->nullable()->after('reference_id');
            $table->foreign('deduction_category_id')->references('id')->on('deduction_categories');
        });
    }

    public function down(): void
    {
        Schema::table('employee_ledger', function (Blueprint $table) {
            $table->dropForeign(['deduction_category_id']);
            $table->dropColumn('deduction_category_id');
        });

        DB::statement(
            "ALTER TABLE employee_ledger MODIFY COLUMN type ".
            "ENUM('opening','wage_earned','piece_earned','overtime','night_allowance','bonus',".
            "'advance','tiffin','payout','fine','adjustment') NOT NULL"
        );

        Schema::dropIfExists('deduction_categories');
    }
};
