<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Import extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'file_name',
        'type',
        'status',
        'total_rows',
        'successful_rows',
        'failed_rows',
        'error_log',
    ];

    protected $casts = [
        'error_log' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user who uploaded this import.
     */
    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope to get imports by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
