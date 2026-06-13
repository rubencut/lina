<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function exportedBy()
    {
        return $this->belongsTo(User::class, 'exported_by');
    }
}
