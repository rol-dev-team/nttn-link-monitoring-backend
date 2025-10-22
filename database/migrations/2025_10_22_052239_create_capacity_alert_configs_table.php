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
        Schema::create('capacity_alert_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activation_plan_id');
            $table->decimal('max_threshold_mbps', 10, 2);
            $table->integer('max_frequency_per_day');
            $table->integer('max_consecutive_days');
            $table->decimal('min_threshold_mbps', 10, 2);
            $table->integer('min_frequency_per_day');
            $table->integer('min_consecutive_days');
            $table->timestamps();

            // Foreign key to partner_activation_plans
            $table->foreign('activation_plan_id')
                ->references('id')
                ->on('partner_activation_plans')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capacity_alert_configs');
    }
};
