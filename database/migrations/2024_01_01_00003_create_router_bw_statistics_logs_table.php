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
        Schema::connection('pgsql')->create('router_bw_statistics_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors');
            $table->foreignId('router_id')->constrained('routers');
            $table->string('host_name');
            $table->string('interface');
            $table->string('category');
            $table->string('category_type', 255);
            $table->string('interface_description')->nullable();
            $table->integer('assigned_capacity')->nullable();
            $table->integer('policer')->nullable();
            $table->decimal('utilization_mb', 12, 2)->nullable();
            $table->timestamp('collected_at');
            $table->timestamp('timestamp')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('router_bw_statistics_logs');
    }
};
