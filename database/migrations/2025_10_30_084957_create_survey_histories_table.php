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
        Schema::create('survey_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sbu_id');
            $table->unsignedBigInteger('link_type_id');
            $table->unsignedBigInteger('aggregator_id');
            $table->unsignedBigInteger('kam_id');
            $table->unsignedBigInteger('nttn_id');

            $table->unsignedBigInteger('nttn_survey_id')->nullable();

            // Decimal/Location Data
            $table->double('nttn_lat')->nullable();
            $table->double('nttn_long')->nullable();
            $table->double('client_lat')->nullable();
            $table->double('client_long')->nullable();

            // Other Fields
            $table->unsignedBigInteger('client_id');
            $table->integer('mac_user')->nullable();
            $table->date('submission');
            $table->string('posted_by')->nullable();

            // Enum
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_histories');
    }
};
