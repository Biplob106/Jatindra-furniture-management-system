<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Machine work cut for somebody else, or for one of our own orders.
     *
     * material_by says who supplied the board. When the customer brings their
     * own, the job is pure machine time and the whole amount is margin against
     * running cost; when the shop supplies it, the material came out of our own
     * stock and was paid for earlier.
     */
    public function up(): void
    {
        Schema::create('cnc_jobs', function (Blueprint $table) {
            $table->id();
            // CNC-2607-0031. Issued by NumberSeries, never from a form.
            $table->string('job_no', 30)->unique();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 20)->nullable();
            // Set when the job is cutting parts for one of our own orders,
            // rather than job work for an outside carpenter.
            $table->unsignedBigInteger('order_id')->nullable();
            $table->date('job_date');
            $table->text('description')->nullable();
            $table->enum('material_by', ['customer', 'shop'])->default('customer');
            $table->enum('rate_type', ['per_sqft', 'per_piece', 'per_hour', 'fixed'])->default('per_sqft');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->decimal('machine_hours', 6, 2)->default(0);
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'delivered', 'cancelled'])->default('pending');
            $table->date('delivery_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('operator_id')->references('id')->on('employees');
            $table->index(['status', 'job_date'], 'idx_status_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cnc_jobs');
    }
};
