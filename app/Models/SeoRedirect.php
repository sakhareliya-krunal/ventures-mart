<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoRedirect extends Model
{
    protected $fillable = [
        'old_path',
        'target_path',
        'status_code',
        'hit_count',
        'is_active',
        'last_hit_at',
    ];

    protected function casts(): array
    {
        return [
            'hit_count' => 'integer',
            'is_active' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }
}
