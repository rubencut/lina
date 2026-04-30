<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Export extends Model
{
    use HasFactory;

    protected $fillable = [
        'exported_by',
        'type',
        'format',
        'file_path',
        'filters',
    ];

    protected $casts = [
        'filters' => 'array',
        'created_at' => 'datetime',
    ];

    const UPDATED_AT = null;

    /**
     * Get the user who exported this.
     */
    public function exportedBy()
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
