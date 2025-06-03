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
        Schema::table('users', function (Blueprint $table) {
            //Add the 'phone_number' column
            // VARCHAR(255) for flexibility in phone number formats
            // unique() ensures no two users have the same phone number
            // nullable() allows users to register without providing a phone number initially
            $table->string('phone_number', 255)->unique()->nullable()->after('email'); // Added after 'email'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //Drop the 'phone_number' column if the migration is rolled back
            $table->dropColumn('phone_number');
        });
    }
};
