<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_documents', function (Blueprint $table) {
            $table->id();
            $table->integer('rider_id');
            $table->enum('document_type', [
                'cnic_front',
                'cnic_back',
                'driving_license',
                'vehicle_registration_book',
                'electricity_bill',
                'profile_picture',
                'vehicle_image'
            ]);
            $table->string('document_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('uploaded_at')->useCurrent();
            $table->timestamp('verified_at')->nullable();
            
            $table->index('rider_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_documents');
    }
};
