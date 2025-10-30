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
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sbu_id')->nullable();
            $table->string('link_type_id')->nullable();
            $table->unsignedBigInteger('aggregator_id')->nullable();
            $table->unsignedBigInteger('kam_id')->nullable();
            $table->unsignedBigInteger('nttn_id')->nullable();
            $table->string('nttn_survey_id')->nullable();
            $table->string('nttn_lat')->nullable();
            $table->string('nttn_long')->nullable();

            $table->unsignedBigInteger('client_id')->nullable();
            $table->string('client_lat')->nullable();
            $table->string('client_long')->nullable();

            $table->string('mac_user')->nullable();
            $table->string('nttn_work_order_id')->nullable();

            $table->integer('request_capacity')->nullable();
            $table->string('shift_capacity')->nullable();
            $table->string('current_capacity')->nullable();

            $table->unsignedBigInteger('rate_id')->nullable();
            $table->unsignedBigInteger('work_order_mac_user')->nullable();

            $table->timestamp('submission')->nullable();
            $table->timestamp('requested_delivery')->nullable();
            $table->timestamp('service_handover')->nullable();
            $table->string('vlan')->nullable();

            $table->string('posted_by')->nullable();

            $table->enum('modify_status', ['upgrade', 'downgrade'])->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
