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
        Schema::create('icmp_alert_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activation_plan_id');
            $table->decimal('latency_threshold_ms', 10, 2);
//            $table->boolean('is_active')->default(true);
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
        Schema::dropIfExists('icmp_alert_configs');
    }
};
