<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('notifications')
            ->where('type', 'sms')
            ->update([
                'type' => 'email',
                'status' => 'failed',
                'sent_at' => null,
            ]);

        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', ['email'])->default('email')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', ['email', 'sms'])->default('email')->change();
        });
    }
};
