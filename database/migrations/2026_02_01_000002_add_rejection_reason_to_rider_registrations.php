<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('rider_registrations', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rider_registrations', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};