<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A reusable, named set of images (e.g. sponsor logos) that overlay windows
 * can pull from by id — upload once, use in many layers.
 */
class Gallery extends Model
{
    protected $fillable = ['name', 'images'];

    protected $casts = ['images' => 'array'];

    protected static function booted(): void
    {
        // Deleting a gallery also removes its image files from the server.
        static::deleting(function (Gallery $gallery) {
            foreach ($gallery->imagePaths() as $path) {
                Storage::disk('public')->delete($path);
            }
        });
    }

    /** @return list<string> ordered image paths */
    public function imagePaths(): array
    {
        return array_values(array_filter($this->images ?? []));
    }
}
