<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_companies', function (Blueprint $table) {
            if (Schema::hasColumn('merchant_companies', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
            if (Schema::hasColumn('merchant_companies', 'account_number')) {
                $table->dropColumn('account_number');
            }
            if (Schema::hasColumn('merchant_companies', 'avg_parcels_per_day')) {
                $table->dropColumn('avg_parcels_per_day');
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchant_companies', function (Blueprint $table) {
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->integer('avg_parcels_per_day')->nullable();
        });
    }
};
