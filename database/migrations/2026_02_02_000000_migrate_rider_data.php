<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Data migration disabled - keeping old tables intact
        // New tables will be empty initially
        // You can manually migrate data later if needed
    }

    public function down(): void
    {
        // Rollback not implemented - data migration is one-way
    }
};
