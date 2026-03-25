<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalTier extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'price',
        'price_suffix',
        'benefits',
        'highlighted',
        'slots_total',
        'slots_taken',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'benefits'    => 'array',
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
}
