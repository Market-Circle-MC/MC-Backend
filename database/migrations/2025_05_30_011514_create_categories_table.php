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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->unique(); // Human-readable name, unique
            $table->string('slug', 255)->unique(); // URL-friendly unique identifier
            $table->text('description')->nullable(); // Brief description of category contents

            // Self-referencing Foreign Key for hierarchical categories
            // Using 'nullable' and 'constrained' with 'onDelete('set null')'
            $table->foreignId('parent_id')
                  ->nullable()
                  ->constrained('categories') // References 'id' on the 'categories' table itself
                  ->onDelete('set null'); // If a parent is deleted, child's parent_id becomes NULL

            $table->string('image_url', 255)->nullable(); // URL to category image
            $table->boolean('is_active')->default(true); // Flag for category visibility

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
