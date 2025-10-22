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
        Schema::create('menu_page_elements', function (Blueprint $table) {
            $table->id();
            $table->string('page_name');
            $table->string('path');
            $table->integer('menu_id')->nullable();
            $table->string('menu_name')->nullable();
            $table->string('menu_icon')->nullable(); // New field
            $table->integer('sub_menu_id')->nullable();
            $table->string('sub_menu_name')->nullable();
            $table->string('sub_menu_icon')->nullable(); // New field
            $table->string('page_icon')->nullable(); // New field
            $table->integer('status')->default(1); // New field with a default value
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_page_elements');
    }
};
