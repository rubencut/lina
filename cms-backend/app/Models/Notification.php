<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'message',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user associated with this notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get pending notifications.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get sent notifications.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }
}
