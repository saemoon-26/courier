<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_companies', function (Blueprint $table) {
            if (!Schema::hasColumn('merchant_companies', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('merchant_companies', function (Blueprint $table) {
            $table->dropTimestamps();
        });
    }
};
