<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the existing 'users' table if it exists.
        // This is useful if you are re-running migrations from scratch.
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // This will be the unique username, all lowercase with no spaces.
            $table->string('first_name')->nullable(); // Added new field
            $table->string('last_name')->nullable(); // Added new field
            $table->string('email')->unique();
            $table->string('mobile')->unique();
            $table->unsignedBigInteger('primary_role_id')->nullable();
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('dept_id')->nullable();
            $table->string('password');
            $table->timestamps();
        });


        // // Insert default admin user
        // DB::table('users')->insert([
        //     'name' => 'admin',
        //     'first_name' => 'Admin',
        //     'last_name' => 'User',
        //     'email' => 'admin@example.com',
        //     'mobile' => '01700000000',
        //     'password' => Hash::make('root@webApp'),
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
