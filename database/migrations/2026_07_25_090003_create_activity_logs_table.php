<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 50)->nullable();
            $table->string('model_type', 100)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id'], 'idx_model');
            $table->index(['user_id', 'created_at'], 'idx_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
