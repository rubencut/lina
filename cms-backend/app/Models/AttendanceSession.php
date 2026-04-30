<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'classroom_id',
        'title',
        'date',
        'start_time',
        'end_time',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'start_time' => 'datetime:H:i',
        'end_time' => 'datetime:H:i',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the classroom associated with this session.
     */
    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Get the user who created this session.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
