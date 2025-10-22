<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        // Create ENUM type for router_status
        DB::connection('pgsql')->statement("CREATE TYPE router_status AS ENUM ('active', 'inactive')");

        Schema::connection('pgsql')->create('routers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            // $table->foreign('vendor_id')->references('id')->on('vendors'); // add later

            $table->string('display_name', 255)->nullable();
            $table->string('ip_address')->unique();
            $table->string('location', 255)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // Indexes
        DB::connection('pgsql')->statement("CREATE INDEX idx_routers_vendor_id ON routers(vendor_id)");
        DB::connection('pgsql')->statement("CREATE INDEX display_name ON routers(display_name)");
        DB::connection('pgsql')->statement("CREATE INDEX idx_routers_status ON routers(status)");
        DB::connection('pgsql')->statement("CREATE INDEX idx_routers_location_status ON routers(location, status)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {
        // Drop indexes
        DB::connection('pgsql')->statement("DROP INDEX IF EXISTS idx_routers_vendor_id");
        DB::connection('pgsql')->statement("DROP INDEX IF EXISTS idx_routers_display_name");
        DB::connection('pgsql')->statement("DROP INDEX IF EXISTS idx_routers_status");
        DB::connection('pgsql')->statement("DROP INDEX IF EXISTS idx_routers_location_status");

        // Drop the table
        Schema::connection('pgsql')->dropIfExists('routers');

        // Drop the ENUM type
        DB::connection('pgsql')->statement("DROP TYPE IF EXISTS router_status");
    }
};
