<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stat extends Model
{
    protected $fillable = [
        'key',
        'value',
        'label_lt',
        'label_en',
    ];

    public static function getValue(string $key): int
    {
        $stat = static::where('key', $key)->first();

        return $stat ? (int) $stat->value : 0;
    }
}
