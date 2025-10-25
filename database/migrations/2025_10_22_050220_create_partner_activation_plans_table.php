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
        Schema::create('partner_activation_plans', function (Blueprint $table) {
            $table->id();
            $table->string('work_order_id')->nullable();
            $table->string('client_id')->nullable();
            $table->string('int_routing_ip')->nullable();
            $table->string('ggc_routing_ip')->nullable();
            $table->string('fna_routing_ip')->nullable();
            $table->string('bcdx_routing_ip')->nullable();
            $table->string('mcdn_routing_ip')->nullable();
            $table->string('nttn_vlan')->nullable();
            $table->string('int_vlan')->nullable();
            $table->string('ggn_vlan')->nullable();
            $table->string('fna_vlan')->nullable();
            $table->string('bcdx_vlan')->nullable();
            $table->string('mcdn_vlan')->nullable();
            $table->string('nas_ip')->unique();
            $table->string('nat_ip')->unique();
            $table->string('connected_ws_name')->nullable();
            $table->string('chr_server')->nullable();
            $table->integer('sw_port')->nullable();
            $table->string('nic_no')->nullable();
            $table->integer('asn')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_activation_plans');
    }
};
