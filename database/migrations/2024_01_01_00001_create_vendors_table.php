<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // Create ENUM type for vendor_status
        DB::connection('pgsql')->statement("CREATE TYPE vendor_status AS ENUM ('active', 'inactive')");

        Schema::connection('pgsql')->create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_name', 255)->unique();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // Index
        $tableName = 'vendors';
        DB::connection('pgsql')->statement("CREATE INDEX idx_vendors_status ON {$tableName}(status)");
    }

    public function down(): void {
        // Drop the index
        DB::connection('pgsql')->statement("DROP INDEX IF EXISTS idx_vendors_status");

        // Drop the table
        Schema::connection('pgsql')->dropIfExists('vendors');

        // Drop the ENUM type
        DB::connection('pgsql')->statement("DROP TYPE IF EXISTS vendor_status");
    }
};
