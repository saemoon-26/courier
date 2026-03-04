<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_banks', function (Blueprint $table) {
            $table->id();
            $table->integer('rider_id')->unique();
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_title');
            $table->timestamps();
            
            $table->index('rider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_banks');
    }
};
