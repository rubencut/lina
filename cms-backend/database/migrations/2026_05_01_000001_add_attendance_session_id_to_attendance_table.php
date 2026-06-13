<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropUnique('attendance_user_id_classroom_id_date_unique');

            if (!Schema::hasColumn('attendance', 'attendance_session_id')) {
                $table->foreignId('attendance_session_id')
                    ->nullable()
                    ->after('classroom_id')
                    ->constrained('attendance_sessions')
                    ->nullOnDelete();
            }

            $table->unique(['user_id', 'classroom_id', 'date', 'attendance_session_id'], 'attendance_user_class_date_session_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            if (Schema::hasColumn('attendance', 'attendance_session_id')) {
                $table->dropUnique('attendance_user_class_date_session_unique');
                $table->dropConstrainedForeignId('attendance_session_id');
            }

            $table->unique(['user_id', 'classroom_id', 'date']);
        });
    }
};
