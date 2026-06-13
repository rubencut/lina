<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    private const VERIFICATION_ROLES = [
        'staff_teacher_supervisor',
        'student_employee_participant',
    ];

    protected $fillable = [
        'name',
        'email',
        'email_verification_code',
        'email_verification_expires_at',
        'password',
        'phone',
        'profile_image',
        'qr_code',
        'classroom_id',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'email_verification_expires_at' => 'datetime',
        'password' => 'hashed',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    public function teacherClassrooms()
    {
        return $this->hasMany(Classroom::class, 'teacher_id');
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function isSuperAdmin()
    {
        return $this->role === 'super_admin';
    }

    public function isStaffTeacherSupervisor()
    {
        return $this->role === 'staff_teacher_supervisor';
    }

    public function isStudentEmployeeParticipant()
    {
        return $this->role === 'student_employee_participant';
    }

    public function needsEmailVerification(): bool
    {
        return in_array($this->role, self::VERIFICATION_ROLES, true);
    }

    public function hasPendingEmailVerification(): bool
    {
        return $this->needsEmailVerification()
            && ! $this->email_verified_at
            && (bool) $this->email_verification_code;
    }

    public function startEmailVerification(): ?string
    {
        if (! $this->needsEmailVerification()) {
            return null;
        }

        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'email_verified_at' => null,
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(30),
        ])->save();

        return $code;
    }

    public function verificationCodeMatches(string $code): bool
    {
        return $this->email_verification_code
            && Hash::check($code, $this->email_verification_code);
    }

    public function completeEmailVerification(): void
    {
        $this->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();
    }
}
