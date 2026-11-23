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

if (!function_exists('nice_title')) {
    /**
     * Titles are often typed in ALL CAPS in the admin. Show them in sentence
     * case (better for reading and for search results) but keep Roman
     * numerals (III etapas) upper-case. Mixed-case titles are left alone.
     */
    function nice_title(?string $title): string
    {
        $title = trim((string) $title);
        if ($title === '' || $title !== mb_strtoupper($title) || !preg_match('/\p{L}{3,}/u', $title)) {
            return $title;
        }
        $s = mb_strtolower($title);
        $s = mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);

        return preg_replace_callback('/\b(i{1,3}|iv|vi{0,3}|ix|x)\b/u', fn ($m) => mb_strtoupper($m[0]), $s);
    }
}
