<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `exports` MODIFY `format` ENUM('Excel', 'PDF', 'CSV') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `exports` MODIFY `format` ENUM('Excel', 'PDF') NOT NULL");
    }
};
