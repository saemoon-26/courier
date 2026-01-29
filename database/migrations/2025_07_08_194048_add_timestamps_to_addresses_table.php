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
        
        if (Schema::hasTable('address')) {
            Schema::table('address', function (Blueprint $table) {
                if (!Schema::hasColumn('address', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('address')) {
            Schema::table('address', function (Blueprint $table) {
                $table->dropTimestamps();
            });
        }
    }
};
