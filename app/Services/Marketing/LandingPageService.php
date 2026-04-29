<?php

namespace App\Services\Marketing;

use App\Models\Marketing\LandingPage;
use Illuminate\Support\Str;

class LandingPageService
{
    /**
     * Sections schema — each block has `type` + data keys.
     *  - heading:     { heading, sub }
     *  - text:        { body (markdown) }
     *  - image:       { src, alt, caption }
     *  - features:    { items: [{icon, title, body}, ...] }
     *  - testimonial: { quote, author, role, avatar }
     *  - faq:         { items: [{q, a}, ...] }
     *  - cta:         { label, url, note }
     */
    public const BLOCK_TYPES = [
        'heading'     => 'Heading',
        'text'        => 'Rich Text',
        'image'       => 'Image',
        'features'    => 'Feature Grid',
        'testimonial' => 'Testimonial',
        'faq'         => 'FAQ',
        'cta'         => 'Call to Action',
    ];

    public function create(array $data, ?int $authorId = null): LandingPage
    {
        $data['slug']       = $this->uniqueSlug($data['slug'] ?? $data['title']);
        $data['author_id']  = $authorId;
        $data['status']     = $data['status'] ?? 'draft';
        $data['theme']      = $data['theme'] ?? 'indigo';
        $data['sections']   = $data['sections'] ?? [];
        $data['seo']        = $data['seo'] ?? [];

        return LandingPage::create($data);
    }

    public function update(LandingPage $lp, array $data): LandingPage
    {
        if (isset($data['slug']) && $data['slug'] !== $lp->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $lp->id);
        }
        $lp->fill($data);
        $lp->save();
        return $lp;
    }

    public function publish(LandingPage $lp): LandingPage
    {
        $lp->status       = 'published';
        $lp->published_at = $lp->published_at ?? now();
        $lp->save();
        return $lp;
    }

    public function archive(LandingPage $lp): LandingPage
    {
        $lp->status = 'archived';
        $lp->save();
        return $lp;
    }

    public function recordView(LandingPage $lp): void
    {
        $lp->increment('views');
    }

    public function recordConversion(LandingPage $lp): void
    {
        $lp->increment('conversions');
    }

    public function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base, '-');
        if ($slug === '') {
            $slug = 'lp-' . Str::random(6);
        }
        $original = $slug;
        $i = 2;
        while (LandingPage::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $original . '-' . $i;
            $i++;
        }
        return $slug;
    }

    /** Normalize sections from admin form to JSON-safe format */
    public function normalizeSections(array $raw): array
    {
        $out = [];
        foreach ($raw as $block) {
            $type = $block['type'] ?? null;
            if (! $type || ! isset(self::BLOCK_TYPES[$type])) continue;
            $data = $block['data'] ?? [];
            $out[] = ['type' => $type, 'data' => $data];
        }
        return $out;
    }

    public function summary(): array
    {
        return [
            'total'       => LandingPage::count(),
            'published'   => LandingPage::where('status', 'published')->count(),
            'drafts'      => LandingPage::where('status', 'draft')->count(),
            'archived'    => LandingPage::where('status', 'archived')->count(),
            'total_views' => (int) LandingPage::sum('views'),
            'total_conv'  => (int) LandingPage::sum('conversions'),
        ];
    }
}
