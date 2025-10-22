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
        // Drop the old page_elements table and create a new one with the combined fields.
        Schema::dropIfExists('page_elements');
        Schema::create('page_elements', function (Blueprint $table) {
            $table->id();
            $table->string('page_name');
            $table->string('page_slug')->unique();
            $table->string('path');
            $table->integer('menu_id')->nullable();
            $table->string('menu_name')->nullable();
            $table->string('menu_icon')->nullable();
            $table->integer('sub_menu_id')->nullable();
            $table->string('sub_menu_name')->nullable();
            $table->string('sub_menu_icon')->nullable();
            $table->string('page_icon')->nullable();
            $table->integer('status')->default(1);
            $table->integer('order_id')->nullable(); // New field
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_elements');
    }
};
