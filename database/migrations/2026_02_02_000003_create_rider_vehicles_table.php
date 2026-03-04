<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_vehicles', function (Blueprint $table) {
            $table->id();
            $table->integer('rider_id');
            $table->string('vehicle_type');
            $table->string('vehicle_brand')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_registration')->nullable();
            $table->string('registration_no')->nullable();
            $table->timestamps();
            
            $table->index('rider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_vehicles');
    }
};
