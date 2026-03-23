<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        $locale = $request->segment(1);

        if (in_array($locale, ['lt', 'en'])) {
            App::setLocale($locale);
            session(['locale' => $locale]);
        } else {
            $sessionLocale = session('locale', 'lt');
            App::setLocale($sessionLocale);
        }

        return $next($request);
    }
}
