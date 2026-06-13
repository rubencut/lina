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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'qr_code')) {
                $table->string('qr_code')->nullable()->unique()->after('profile_image');
            }
            if (! Schema::hasColumn('users', 'classroom_id')) {
                $table->foreignId('classroom_id')->nullable()->constrained('classrooms')->nullOnDelete()->after('qr_code');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['super_admin', 'staff_teacher_supervisor', 'student_employee_participant'])->default('student_employee_participant')->after('email');
            }
            if (! Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'inactive', 'suspended'])->default('active')->after('classroom_id');
            }

            if (Schema::hasColumn('users', 'role')) {
                $table->index('role');
            }
            if (Schema::hasColumn('users', 'classroom_id')) {
                $table->index('classroom_id');
            }
            if (Schema::hasColumn('users', 'status')) {
                $table->index('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndexIfExists(['role']);
            $table->dropIndexIfExists(['classroom_id']);
            $table->dropIndexIfExists(['status']);
            $table->dropForeignKeyIfExists('users_classroom_id_foreign');

            $table->dropColumn([
                'phone',
                'profile_image',
                'qr_code',
                'classroom_id',
                'role',
                'status',
            ]);
        });
    }
};
