<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\Product;
use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeoScoreService
{
    public function score(?SeoMetadata $seo, Model|string|null $subject = null, array $faqs = []): int
    {
        return $this->evaluate($seo, $subject, $faqs)['score'];
    }

    /**
     * @return array{score: int, checks: list<array{id: string, label: string, pass: bool, weight: int, hint: string}>}
     */
    public function evaluate(?SeoMetadata $seo, Model|string|null $subject = null, array $faqs = []): array
    {
        $effective = $this->effectiveFields($seo, $subject);
        $title = $effective['title'];
        $description = $effective['description'];
        $keyword = Str::lower(trim((string) ($seo?->focus_keyword ?? '')));
        $imageAlt = trim((string) ($seo?->image_alt_text ?? ''));
        $canonical = trim((string) ($seo?->canonical_url ?? ''));
        $robots = trim((string) ($seo?->meta_robots ?? 'index,follow'));
        $ogImage = trim((string) ($seo?->og_image ?: $effective['image']));
        $schema = $seo?->custom_schema ?? null;
        $visibleFaqs = collect($faqs)->filter(fn ($faq) => filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))->count();

        $titleLen = mb_strlen($title);
        $descLen = mb_strlen($description);
        $keywordInCopy = $keyword !== '' && Str::contains(Str::lower($title.' '.$description), $keyword);

        $checks = [
            [
                'id' => 'title_length',
                'label' => 'SEO title length (25–65 characters)',
                'pass' => $titleLen >= 25 && $titleLen <= 65,
                'weight' => 18,
                'hint' => $title === ''
                    ? 'Add an SEO title or rely on the generated page title.'
                    : "Current length: {$titleLen}. Aim for 25–65 characters.",
            ],
            [
                'id' => 'description_length',
                'label' => 'Meta description length (80–165 characters)',
                'pass' => $descLen >= 80 && $descLen <= 165,
                'weight' => 18,
                'hint' => $description === ''
                    ? 'Add a meta description summarizing the page for searchers.'
                    : "Current length: {$descLen}. Aim for 80–165 characters.",
            ],
            [
                'id' => 'focus_keyword',
                'label' => 'Focus keyword set',
                'pass' => $keyword !== '',
                'weight' => 12,
                'hint' => 'Set a primary focus keyword for this page.',
            ],
            [
                'id' => 'keyword_in_copy',
                'label' => 'Focus keyword appears in title or description',
                'pass' => $keywordInCopy,
                'weight' => 10,
                'hint' => 'Include the focus keyword naturally in the SEO title or meta description.',
            ],
            [
                'id' => 'canonical',
                'label' => 'Canonical URL set (or auto-generated)',
                'pass' => $canonical !== '' || $subject !== null,
                'weight' => 10,
                'hint' => 'Leave blank to auto-generate, or set an absolute/relative canonical URL.',
            ],
            [
                'id' => 'robots',
                'label' => 'Robots meta configured',
                'pass' => $robots !== '',
                'weight' => 8,
                'hint' => 'Use index,follow for public pages.',
            ],
            [
                'id' => 'image_alt',
                'label' => 'Image alt text set',
                'pass' => $imageAlt !== '',
                'weight' => 10,
                'hint' => 'Describe the primary image for accessibility and image search.',
            ],
            [
                'id' => 'og_image',
                'label' => 'Open Graph image available',
                'pass' => $ogImage !== '',
                'weight' => 8,
                'hint' => 'Set an OG image or ensure the page has a default product/cover image.',
            ],
            [
                'id' => 'faqs',
                'label' => 'At least one FAQ',
                'pass' => $visibleFaqs > 0,
                'weight' => 8,
                'hint' => 'Add 1–2 FAQs to unlock FAQ rich results where relevant.',
            ],
            [
                'id' => 'custom_schema',
                'label' => 'Custom schema JSON (optional bonus)',
                'pass' => is_array($schema) && $schema !== [],
                'weight' => 6,
                'hint' => 'Optional: add valid JSON-LD for advanced markup.',
            ],
        ];

        // Partial credit for title/description that exist but are outside ideal length
        $score = 0;
        foreach ($checks as $check) {
            if ($check['pass']) {
                $score += $check['weight'];
                continue;
            }
            if ($check['id'] === 'title_length' && $title !== '') {
                $score += 8;
            }
            if ($check['id'] === 'description_length' && $description !== '') {
                $score += 8;
            }
        }

        return [
            'score' => min(100, $score),
            'checks' => $checks,
        ];
    }

    /**
     * @return array{title: string, description: string, image: string}
     */
    private function effectiveFields(?SeoMetadata $seo, Model|string|null $subject): array
    {
        $title = trim((string) ($seo?->title ?? ''));
        $description = trim((string) ($seo?->meta_description ?? ''));
        $image = trim((string) ($seo?->og_image ?? ''));

        if ($description === '' && filled($seo?->ai_summary)) {
            $description = trim((string) $seo->ai_summary);
        }

        if ($subject instanceof Product) {
            $title = $title !== '' ? $title : trim((string) $subject->name);
            if ($description === '') {
                $description = trim(preg_replace('/\s+/', ' ', strip_tags((string) $subject->description)));
            }
            $image = $image !== '' ? $image : trim((string) ($subject->image ?? ''));
        } elseif ($subject instanceof Category) {
            $title = $title !== '' ? $title : trim((string) $subject->name);
            $description = $description !== '' ? $description : trim((string) ($subject->description ?? ''));
            $image = $image !== '' ? $image : trim((string) ($subject->image ?? ''));
        } elseif ($subject instanceof Post) {
            $title = $title !== '' ? $title : trim((string) $subject->title);
            $description = $description !== '' ? $description : trim((string) ($subject->excerpt ?? ''));
            $image = $image !== '' ? $image : trim((string) ($subject->cover_image ?? ''));
        }

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
        ];
    }
}
