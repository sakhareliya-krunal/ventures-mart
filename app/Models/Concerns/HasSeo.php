<?php

namespace App\Models\Concerns;

use App\Models\SeoFaq;
use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSeo
{
    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(SeoMetadata::class, 'seoable');
    }

    public function seoFaqs(): MorphMany
    {
        return $this->morphMany(SeoFaq::class, 'faqable')->orderBy('sort_order')->orderBy('id');
    }
}
