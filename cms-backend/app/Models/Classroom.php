<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'teacher_id',
        'description',
        'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the teacher of this classroom.
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Get all users in this classroom.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get attendance records for this classroom.
     */
    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Get sessions for this classroom.
     */
    public function sessions()
    {
        return $this->hasMany(AttendanceSession::class);
    }
}
