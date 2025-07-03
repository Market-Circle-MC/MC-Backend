<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('restrict'); // Changed to customer_id, NOT NULL, onDelete restrict
            $table->string('order_number', 50)->unique();
            $table->foreignId('delivery_address_id')->constrained('addresses')->onDelete('restrict'); // Added, NOT NULL, onDelete restrict
            $table->foreignId('delivery_option_id')->constrained('delivery_options')->onDelete('restrict'); // NOT NULL, onDelete restrict
            $table->decimal('order_total', 12, 2);
            $table->string('order_status', 50)->default('pending');
            $table->string('payment_status', 50)->default('unpaid');
            $table->string('payment_method', 100)->nullable(); 
            $table->string('payment_gateway_transaction_id', 255)->nullable();
            $table->jsonb('payment_details')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('ordered_at')->useCurrent();
            $table->timestamp('dispatched_at')->nullable();
            $table->string('delivery_tracking_number', 255)->nullable();
            $table->string('delivery_service', 255)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
