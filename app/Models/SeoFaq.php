<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoFaq extends Model
{
    protected $fillable = [
        'locale',
        'page_key',
        'question',
        'answer',
        'sort_order',
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }
}
