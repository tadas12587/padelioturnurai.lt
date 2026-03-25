<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalTier extends Model
{
    protected $fillable = [
        'name', 'name_en',
        'tagline', 'tagline_en',
        'price', 'price_suffix',
        'benefits', 'benefits_en',
        'highlighted',
        'slots_total', 'slots_taken',
        'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'benefits'    => 'array',
            'benefits_en' => 'array',
            'highlighted' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    public function getSlotsLeftAttribute(): ?int
    {
        if ($this->slots_total === null) {
            return null;
        }
        return max(0, $this->slots_total - $this->slots_taken);
    }

    /** Returns locale-aware name */
    public function localeName(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        return ($locale === 'en' && $this->name_en) ? $this->name_en : $this->name;
    }

    /** Returns locale-aware tagline */
    public function localeTagline(?string $locale = null): ?string
    {
        $locale = $locale ?? app()->getLocale();
        return ($locale === 'en' && $this->tagline_en) ? $this->tagline_en : $this->tagline;
    }

    /** Returns locale-aware benefits array */
    public function localeBenefits(?string $locale = null): array
    {
        $locale = $locale ?? app()->getLocale();
        $en = $this->benefits_en;
        if ($locale === 'en' && ! empty($en)) {
            return $en;
        }
        return $this->benefits ?? [];
    }
}
