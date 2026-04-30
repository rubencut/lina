<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'attendance';

    protected $fillable = [
        'user_id',
        'classroom_id',
        'date',
        'time_in',
        'time_out',
        'status',
        'remarks',
        'recorded_by',
    ];

    protected $casts = [
        'date' => 'date',
        'time_in' => 'datetime:H:i',
        'time_out' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this attendance record.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the classroom associated with this attendance record.
     */
    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get the user who recorded this attendance.
     */
    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Scope to filter by date.
     */
    public function scopeByDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by classroom.
     */
    public function scopeByClassroom($query, $classroomId)
    {
        return $query->where('classroom_id', $classroomId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
