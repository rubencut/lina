<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'model',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    const UPDATED_AT = null;

    /**
     * Get the user associated with this audit log.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to filter by action.
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by model.
     */
    public function scopeByModel($query, $model)
    {
        return $query->where('model', $model);
    }
}
