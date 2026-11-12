<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushSubscription extends Model
{
    protected $fillable = ['tournament_external_id', 'player', 'player_key', 'endpoint_hash', 'endpoint', 'p256dh', 'auth'];

    /** Same folding the page uses for names: lower-case, no Lithuanian diacritics. */
    public static function key(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = class_exists(\Normalizer::class)
            ? preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize($n, \Normalizer::FORM_D))
            : strtr($n, ['ą' => 'a', 'č' => 'c', 'ę' => 'e', 'ė' => 'e', 'į' => 'i', 'š' => 's', 'ų' => 'u', 'ū' => 'u', 'ž' => 'z']);

        return preg_replace('/\s+/', ' ', $n);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PushMessage::class);
    }

    public function toWebPush(): array
    {
        return ['endpoint' => $this->endpoint, 'keys' => ['p256dh' => $this->p256dh, 'auth' => $this->auth]];
    }
}
