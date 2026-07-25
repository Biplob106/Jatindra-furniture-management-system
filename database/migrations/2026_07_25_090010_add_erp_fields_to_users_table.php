<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // phone is the login key per docs/schema.md, but existing rows have no
        // value for it. Add it nullable, backfill, then tighten to NOT NULL.
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('name');
            $table->unsignedBigInteger('shop_id')->nullable()->after('password');
            $table->boolean('is_active')->default(true)->after('shop_id');
            $table->timestamp('last_login_at')->nullable()->after('is_active');
        });

        // Done in PHP rather than SQL so it runs on sqlite as well as MySQL.
        DB::table('users')->whereNull('phone')->orderBy('id')->each(function ($user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update(['phone' => str_pad((string) $user->id, 11, '0', STR_PAD_LEFT)]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable(false)->change();
            $table->string('email', 150)->nullable()->change();

            $table->unique('phone');
            $table->foreign('shop_id')->references('id')->on('shops');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['shop_id']);
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'shop_id', 'is_active', 'last_login_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
