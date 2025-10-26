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
        Schema::create('nas_icmp_timeouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_plan_id')
                  ->constrained('partner_activation_plans')
                  ->onDelete('cascade');

            $table->dateTime('timeout_start');
            $table->dateTime('timeout_end');

            $table->string('timeout_duration');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nas_icmp_timeouts');
    }
};
