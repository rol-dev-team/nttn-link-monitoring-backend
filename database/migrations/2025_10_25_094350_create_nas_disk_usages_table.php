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
        Schema::create('nas_disk_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_plan_id')
                  ->constrained('partner_activation_plans')
                  ->onDelete('cascade');
            $table->string('disk_size');
            $table->string('disk_used');

            $table->dateTime('collected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nas_disk_usages');
    }
};
