<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Users table indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasIndex('users', 'users_email_index')) {
                    $table->index('email');
                }
                if (!Schema::hasIndex('users', 'users_role_index')) {
                    $table->index('role');
                }
                if (Schema::hasColumn('users', 'company_id') && !Schema::hasIndex('users', 'users_company_id_index')) {
                    $table->index('company_id');
                }
            });
        }

        // Parcel table indexes
        if (Schema::hasTable('parcel')) {
            Schema::table('parcel', function (Blueprint $table) {
                if (!Schema::hasIndex('parcel', 'parcel_merchant_id_index')) {
                    $table->index('merchant_id');
                }
                if (!Schema::hasIndex('parcel', 'parcel_assigned_to_index')) {
                    $table->index('assigned_to');
                }
                if (!Schema::hasIndex('parcel', 'parcel_parcel_status_index')) {
                    $table->index('parcel_status');
                }
                if (!Schema::hasIndex('parcel', 'parcel_tracking_code_index')) {
                    $table->index('tracking_code');
                }
            });
        }

        // Parcel details indexes
        if (Schema::hasTable('parcel_details')) {
            Schema::table('parcel_details', function (Blueprint $table) {
                if (!Schema::hasIndex('parcel_details', 'parcel_details_parcel_id_index')) {
                    $table->index('parcel_id');
                }
            });
        }

        // Address table indexes
        if (Schema::hasTable('address')) {
            Schema::table('address', function (Blueprint $table) {
                if (!Schema::hasIndex('address', 'address_user_id_index')) {
                    $table->index('user_id');
                }
                if (!Schema::hasIndex('address', 'address_city_index')) {
                    $table->index('city');
                }
            });
        }

        // Merchant companies indexes
        if (Schema::hasTable('merchant_companies')) {
            Schema::table('merchant_companies', function (Blueprint $table) {
                if (!Schema::hasIndex('merchant_companies', 'merchant_companies_company_name_index')) {
                    $table->index('company_name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['email']);
                $table->dropIndex(['role']);
                if (Schema::hasColumn('users', 'company_id')) {
                    $table->dropIndex(['company_id']);
                }
            });
        }

        if (Schema::hasTable('parcel')) {
            Schema::table('parcel', function (Blueprint $table) {
                $table->dropIndex(['merchant_id']);
                $table->dropIndex(['assigned_to']);
                $table->dropIndex(['parcel_status']);
                $table->dropIndex(['tracking_code']);
            });
        }

        if (Schema::hasTable('parcel_details')) {
            Schema::table('parcel_details', function (Blueprint $table) {
                $table->dropIndex(['parcel_id']);
            });
        }

        if (Schema::hasTable('address')) {
            Schema::table('address', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropIndex(['city']);
            });
        }

        if (Schema::hasTable('merchant_companies')) {
            Schema::table('merchant_companies', function (Blueprint $table) {
                $table->dropIndex(['company_name']);
            });
        }
    }
};