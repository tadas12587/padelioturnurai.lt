<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushMessage extends Model
{
    protected $fillable = ['push_subscription_id', 'app_device_id', 'dedupe_key', 'kind', 'title', 'body', 'url', 'status', 'attempts', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class, 'push_subscription_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AppDevice::class, 'app_device_id');
    }
}
