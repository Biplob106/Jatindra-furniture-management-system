<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('type', ['cash', 'mobile_banking', 'bank']);
            $table->string('account_no', 50)->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            // The one deliberate running-balance column. CashService maintains it
            // inside the same transaction as the transactions row; nothing else writes it.
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
