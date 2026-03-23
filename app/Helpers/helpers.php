<?php

if (!function_exists('lroute')) {
    function lroute(string $name, mixed $parameters = [], bool $absolute = true): string
    {
        $locale = app()->getLocale();

        if ($locale === 'en') {
            $localeName = $name . '.locale';
            if (\Illuminate\Support\Facades\Route::has($localeName)) {
                $params = is_array($parameters)
                    ? array_merge(['locale' => $locale], $parameters)
                    : array_merge(['locale' => $locale], (array) $parameters);
                return route($localeName, $params, $absolute);
            }
        }

        return route($name, $parameters, $absolute);
    }
}
