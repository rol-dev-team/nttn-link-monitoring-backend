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
        Schema::create('partner_drop_device_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activation_plan_id');
            $table->string('device_ip');
            $table->string('usage_vlan');
            $table->string('connected_port');
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
        Schema::dropIfExists('partner_drop_device_configs');
    }
};
