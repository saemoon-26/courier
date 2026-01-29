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
        Schema::create('parcel_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('parcel_id'); // Reference to parcel table (no foreign key constraint)
            $table->string('code', 4); // 4-digit code
            $table->timestamps();
            
            // Ensure unique code for each parcel (no foreign key constraint)
            $table->unique('parcel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_codes');
    }
};
