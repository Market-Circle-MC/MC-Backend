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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->unique(); // Foreign key to users table with unique constraint
            $table->string('customer_type', 50); //  'restaurant', 'family', 'individual_bulk'
            $table->string('business_name')->nullable(); // NULL for 'family' or 'individual_bulk'
            $table->string('ghanapost_gps_address')->nullable(); // Official Ghana Post GPS digital address
            $table->string('digital_address')->nullable(); // More generalized digital address
            $table->string('tax_id')->nullable(); // TIN for 'restaurant' type
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();
            $table->timestamps();

            // Indexes for better querying
            $table->index('customer_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
