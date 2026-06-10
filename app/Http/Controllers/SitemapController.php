<?php

namespace App\Http\Controllers;

use App\Models\News;
use App\Models\Tournament;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [];

        // Static pages (lt default + en prefix)
        $staticPaths = [
            ''            => ['priority' => '1.0', 'changefreq' => 'weekly'],
            'turnyrai'    => ['priority' => '0.9', 'changefreq' => 'weekly'],
            'naujienos'   => ['priority' => '0.8', 'changefreq' => 'weekly'],
            'kontaktai'   => ['priority' => '0.5', 'changefreq' => 'yearly'],
            'tapk-remeju' => ['priority' => '0.6', 'changefreq' => 'monthly'],
        ];

        foreach ($staticPaths as $path => $meta) {
            $urls[] = [
                'loc'        => url('/' . $path),
                'alternates' => [
                    'lt' => url('/' . $path),
                    'en' => url('/en' . ($path ? '/' . $path : '')),
                ],
            ] + $meta;
        }

        // Tournaments
        $tournaments = Tournament::orderBy('date_start', 'desc')->get();
        foreach ($tournaments as $tournament) {
            $urls[] = [
                'loc'        => url('/turnyrai/' . $tournament->slug),
                'lastmod'    => $tournament->updated_at?->toAtomString(),
                'priority'   => $tournament->status === 'past' ? '0.5' : '0.9',
                'changefreq' => $tournament->status === 'past' ? 'yearly' : 'weekly',
                'alternates' => [
                    'lt' => url('/turnyrai/' . $tournament->slug),
                    'en' => url('/en/turnyrai/' . $tournament->slug),
                ],
            ];
        }

        // Published news
        $news = News::published()->orderBy('published_at', 'desc')->get();
        foreach ($news as $article) {
            $urls[] = [
                'loc'        => url('/naujienos/' . $article->slug),
                'lastmod'    => $article->updated_at?->toAtomString(),
                'priority'   => '0.7',
                'changefreq' => 'monthly',
                'alternates' => [
                    'lt' => url('/naujienos/' . $article->slug),
                    'en' => url('/en/naujienos/' . $article->slug),
                ],
            ];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($urls as $u) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . e($u['loc']) . "</loc>\n";
            foreach ($u['alternates'] ?? [] as $lang => $href) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="' . $lang . '" href="' . e($href) . "\"/>\n";
            }
            if (!empty($u['lastmod'])) {
                $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
            }
            if (!empty($u['changefreq'])) {
                $xml .= '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
            }
            if (!empty($u['priority'])) {
                $xml .= '    <priority>' . $u['priority'] . "</priority>\n";
            }
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
