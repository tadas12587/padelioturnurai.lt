<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reusable, named set of images (e.g. sponsor logos) that overlay windows
 * can pull from by id — upload once, use in many layers.
 */
class Gallery extends Model
{
    protected $fillable = ['name', 'images'];

    protected $casts = ['images' => 'array'];

    /** @return list<string> ordered image paths */
    public function imagePaths(): array
    {
        return array_values(array_filter($this->images ?? []));
    }
}
