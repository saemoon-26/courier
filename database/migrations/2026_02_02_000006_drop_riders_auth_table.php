<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep riders_auth table - don't drop it
        // This migration is now empty - tables will coexist
    }

    public function down(): void
    {
        Schema::create('riders_auth', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('mobile_primary');
            $table->string('city');
            $table->string('state');
            $table->string('vehicle_type');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }
};
