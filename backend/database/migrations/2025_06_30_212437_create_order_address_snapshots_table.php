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
        Schema::create('order_address_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('address_type', 50); // 'Shipping' or 'Billing'
            $table->string('recipient_name', 255);
            $table->string('phone_number', 20)->nullable(); // Phone number for this specific delivery
            $table->string('address_line1', 255);
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100);
            $table->string('region', 100); 
            $table->string('country', 100);
            $table->string('ghanapost_gps_address', 255)->nullable();
            $table->string('digital_address_description', 255)->nullable();
            $table->text('delivery_instructions')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'address_type']);
        });
            
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_address_snapshots');
    }
};
