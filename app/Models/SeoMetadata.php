<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMetadata extends Model
{
    protected $fillable = [
        'page_key',
        'locale',
        'title',
        'meta_description',
        'focus_keyword',
        'seo_slug',
        'canonical_url',
        'meta_robots',
        'og_title',
        'og_description',
        'og_image',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'image_alt_text',
        'ai_summary',
        'ai_highlights',
        'custom_schema',
        'score',
        'scored_at',
    ];

    protected function casts(): array
    {
        return [
            'ai_highlights' => 'array',
            'custom_schema' => 'array',
            'score' => 'integer',
            'scored_at' => 'datetime',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
