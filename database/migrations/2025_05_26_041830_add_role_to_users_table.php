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
            //Add the 'role' column
            // VARCHAR(50) is suitable for roles like 'admin', 'customer'
            // NOT NULL means it must have a value
            // DEFAULT 'customer' sets the default for new users
            $table->string('role', 50)->default('customer')->after('password'); // Added after 'password' for logical order
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //Drop the 'role' column if the migration is rolled back
            $table->dropColumn('role');
        });
    }
};
