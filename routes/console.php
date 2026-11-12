<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Host CRON (freehosting.lt panel, every minute) runs `schedule:run`.
Schedule::command('tournated:sync')->everyMinute()->withoutOverlapping(10);

// Queued phone notifications are sent from the web context (the host's CLI
// cannot create the EC keys Web Push needs), so call our own endpoint.
Schedule::call(function () {
    $token = config('services.push.dispatch_token');
    if ($token && config('services.push.vapid_public') && \App\Models\PushMessage::where('status', 'pending')->exists()) {
        \Illuminate\Support\Facades\Http::withHeaders(['X-Push-Token' => $token])->timeout(50)->post(route('push.dispatch'));
    }
})->everyMinute()->name('push-dispatch')->withoutOverlapping(5);
