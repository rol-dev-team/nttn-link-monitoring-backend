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
        Schema::create('page_roles', function (Blueprint $table) {
            // Foreign key for the page_elements table
            $table->foreignId('page_element_id')
                ->constrained('page_elements')
                ->onDelete('cascade');

            // Foreign key for the Spatie roles table
            $table->foreignId('role_id')
                ->constrained('roles')
                ->onDelete('cascade');

            // Set the composite primary key for the pivot table
            $table->primary(['page_element_id', 'role_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_roles');
    }
};
