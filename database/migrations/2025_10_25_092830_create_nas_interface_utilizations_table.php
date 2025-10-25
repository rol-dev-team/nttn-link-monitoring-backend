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
        Schema::create('nas_interface_utilizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_plan_id')
                  ->constrained('partner_activation_plans')
                  ->onDelete('cascade');

            $table->string('interface_port');

            $table->string('max_download_mbps')->nullable();
            $table->string('max_upload_mbps')->nullable();

            $table->dateTime('max_download_collected_at');
            $table->dateTime('max_upload_collected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nas_interface_utilizations');
    }
};
