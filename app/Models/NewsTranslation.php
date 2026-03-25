<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsTranslation extends Model
{
    protected $table = 'news_translations';

    protected $fillable = [
        'news_id',
        'locale',
        'title',
        'excerpt',
        'content',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }
}
