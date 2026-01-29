<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel', function (Blueprint $table) {
            $table->decimal('pickup_lat', 10, 8)->nullable();
            $table->decimal('pickup_lng', 11, 8)->nullable();
            $table->string('zone', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parcel', function (Blueprint $table) {
            $table->dropColumn(['pickup_lat', 'pickup_lng', 'zone']);
        });
    }
};
