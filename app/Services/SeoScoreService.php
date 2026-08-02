<?php

namespace App\Services;

use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoScoreService
{
    public function score(?SeoMetadata $seo, Model|string|null $subject = null, array $faqs = []): int
    {
        $title = trim((string) ($seo?->title ?? ''));
        $description = trim((string) ($seo?->meta_description ?? ''));
        $keyword = Str::lower(trim((string) ($seo?->focus_keyword ?? '')));
        $imageAlt = trim((string) ($seo?->image_alt_text ?? ''));
        $canonical = trim((string) ($seo?->canonical_url ?? ''));
        $robots = trim((string) ($seo?->meta_robots ?? ''));
        $schema = $seo?->custom_schema ?? null;

        $score = 0;
        $score += mb_strlen($title) >= 25 && mb_strlen($title) <= 65 ? 18 : ($title !== '' ? 8 : 0);
        $score += mb_strlen($description) >= 80 && mb_strlen($description) <= 165 ? 18 : ($description !== '' ? 8 : 0);
        $score += $keyword !== '' ? 12 : 0;
        $score += $keyword !== '' && Str::contains(Str::lower($title.' '.$description), $keyword) ? 10 : 0;
        $score += $canonical !== '' ? 10 : 0;
        $score += $robots !== '' ? 8 : 0;
        $score += $imageAlt !== '' ? 10 : 0;
        $score += count($faqs) > 0 ? 8 : 0;
        $score += is_array($schema) && $schema !== [] ? 6 : 0;

        return min(100, $score);
    }
}
