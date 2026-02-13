<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('father_name')->nullable();
            $table->string('email')->unique();
            $table->string('mobile_primary');
            $table->string('mobile_alternate')->nullable();
            $table->string('cnic_number')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('vehicle_brand')->nullable();
            $table->string('vehicle_model')->nullable();
            $table->string('vehicle_registration')->nullable();
            $table->string('driving_license_number')->nullable();
            $table->string('city');
            $table->string('state');
            $table->text('address');
            $table->string('country')->default('Pakistan');
            $table->string('zipcode')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_title')->nullable();
            // Document paths
            $table->string('profile_picture')->nullable();
            $table->string('cnic_front_image')->nullable();
            $table->string('cnic_back_image')->nullable();
            $table->string('driving_license_image')->nullable();
            $table->string('vehicle_registration_book')->nullable();
            $table->string('vehicle_image')->nullable();
            $table->string('electricity_bill')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_registrations');
    }
};
