<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kalnoy\Nestedset\NestedSet;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedInteger('_lft')->nullable(); // Can be null for roots if needed
            $table->unsignedInteger('_rgt')->nullable(); // Can be null for roots if needed

            // Add index for performance (recommended by NestedSet)
            $table->index(['_lft', '_rgt']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            
            $table->dropColumn(['_lft', '_rgt']);

            
            $table->dropIndex(['_lft', '_rgt']);
        });
    }
};
