<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riders', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unique();
            $table->string('father_name')->nullable();
            $table->string('mobile_primary');
            $table->string('mobile_alternate')->nullable();
            $table->string('cnic_number')->unique();
            $table->string('driving_license_number')->unique();
            $table->timestamps();
            
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riders');
    }
};
