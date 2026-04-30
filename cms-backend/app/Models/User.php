<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'profile_image', 'qr_code', 'classroom_id', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the classroom this user is assigned to.
     */
    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get classrooms where this user is a teacher.
     */
    public function teacherClassrooms()
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    /**
     * Get attendance records for this user.
     */
    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get attendance records recorded by this user.
     */
    public function recordedAttendance()
    {
        return $this->hasMany(Attendance::class, 'recorded_by');
    }

    /**
     * Get notifications for this user.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get imports by this user.
     */
    public function imports()
    {
        return $this->hasMany(Import::class, 'uploaded_by');
    }

    /**
     * Get exports by this user.
     */
    public function exports()
    {
        return $this->hasMany(Export::class, 'exported_by');
    }

    /**
     * Get audit logs for this user.
     */
    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get attendance sessions created by this user.
     */
    public function createdSessions()
    {
        return $this->hasMany(AttendanceSession::class, 'created_by');
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Check if user is super admin.
     */
    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    /**
     * Check if user is staff/teacher/supervisor.
     */
    public function isStaffTeacherSupervisor()
    {
        return $this->role === 'staff_teacher_supervisor';
    }

    /**
     * Check if user is student/employee/participant.
     */
    public function isStudentEmployeeParticipant()
    {
        return $this->role === 'student_employee_participant';
    }
}
