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
        Schema::create('partner_infos', function (Blueprint $table) {
            $table->id();
            $table->string('word_order_id')->nullable();
            $table->string('network_code')->nullable();
            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('router_identity')->nullable();
            $table->unsignedBigInteger('technical_kam_id')->nullable();
            $table->unsignedBigInteger('radius_server_id')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('technical_kam_id')
                ->references('id')
                ->on('technical_kams')
                ->onDelete('set null');

            $table->foreign('radius_server_id')
                ->references('id')
                ->on('radius_server_ips')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_infos');
    }
};
