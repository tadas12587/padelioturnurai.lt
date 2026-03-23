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

        if ($locale === 'en') {
            App::setLocale('en');
        } else {
            App::setLocale('lt');
        }

        return $next($request);
    }
}
