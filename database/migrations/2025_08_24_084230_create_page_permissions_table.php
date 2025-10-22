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
        Schema::create('page_permissions', function (Blueprint $table) {
            // Foreign key for the page_elements table
            $table->foreignId('page_element_id')
                ->constrained('page_elements')
                ->onDelete('cascade');

            // Foreign key for the Spatie permissions table
            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->onDelete('cascade');

            // Set the composite primary key for the pivot table
            $table->primary(['page_element_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_permissions');
    }
};
