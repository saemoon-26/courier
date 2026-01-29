<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel', function (Blueprint $table) {
            $table->string('pickup_city', 100)->nullable()->after('pickup_location');
            $table->string('dropoff_city', 100)->nullable()->after('dropoff_location');
        });
    }

    public function down(): void
    {
        Schema::table('parcel', function (Blueprint $table) {
            $table->dropColumn(['pickup_city', 'dropoff_city']);
        });
    }
};
