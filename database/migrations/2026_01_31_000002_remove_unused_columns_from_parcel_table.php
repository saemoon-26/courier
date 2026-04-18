<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel', function (Blueprint $table) {
            // Only remove dropoff columns, keep pickup coordinates
            $table->dropColumn(['dropoff_location', 'dropoff_city', 'zone']);
        });
    }

    public function down(): void
    {
        Schema::table('parcel', function (Blueprint $table) {
            $table->string('dropoff_location')->nullable();
            $table->string('dropoff_city')->nullable();
            $table->string('zone')->nullable();
        });
    }
};
