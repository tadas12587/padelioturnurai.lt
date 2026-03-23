<?php

namespace App\Providers;

use App\Mail\NativeMailTransport;
use App\Models\Setting;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register custom PHP mail() transport (works without proc_open)
        Mail::extend('phpmail', fn () => new NativeMailTransport());

        // Add phpmail mailer definition if not already present
        if (! config('mail.mailers.phpmail')) {
            config(['mail.mailers.phpmail' => ['transport' => 'phpmail']]);
        }

        // Override mail config with values stored in the database (if available)
        Setting::applyMailConfig();
    }
}
