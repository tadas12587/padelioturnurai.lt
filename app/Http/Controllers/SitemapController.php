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
                'loc'        => url('/turnyrai/' . rawurlencode($tournament->slug)),
                'lastmod'    => $tournament->updated_at?->toAtomString(),
                'priority'   => $tournament->status === 'past' ? '0.5' : '0.9',
                'changefreq' => $tournament->status === 'past' ? 'yearly' : 'weekly',
                'alternates' => [
                    'lt' => url('/turnyrai/' . rawurlencode($tournament->slug)),
                    'en' => url('/en/turnyrai/' . rawurlencode($tournament->slug)),
                ],
            ];
        }

        // Published news
        $news = News::published()->orderBy('published_at', 'desc')->get();
        foreach ($news as $article) {
            $urls[] = [
                'loc'        => url('/naujienos/' . rawurlencode($article->slug)),
                'lastmod'    => $article->updated_at?->toAtomString(),
                'priority'   => '0.7',
                'changefreq' => 'monthly',
                'alternates' => [
                    'lt' => url('/naujienos/' . rawurlencode($article->slug)),
                    'en' => url('/en/naujienos/' . rawurlencode($article->slug)),
                ],
            ];
        }

        // Live schedules (players search "<tournament> grafikas / rezultatai")
        $schedules = Tournament::whereNotNull('tournated_id')->where('show_schedule', true)
            ->whereIn('tournated_id', \App\Models\ScheduleSnapshot::pluck('tournament_external_id'))
            ->orderBy('date_start', 'desc')->get();
        foreach ($schedules as $tournament) {
            $urls[] = [
                'loc'        => route('schedule.show', $tournament->tournated_id),
                'lastmod'    => $tournament->updated_at?->toAtomString(),
                'priority'   => $tournament->status === 'past' ? '0.4' : '0.8',
                'changefreq' => $tournament->status === 'past' ? 'yearly' : 'daily',
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

    /**
     * /llms.txt – a short plain-text guide for AI assistants (llmstxt.org):
     * what the site is and where the useful pages are.
     */
    public function llms(): Response
    {
        $t = fn ($tour) => nice_title($tour->translation('lt')?->title ?? $tour->slug);
        $upcoming = Tournament::with('translations')->whereIn('status', ['active', 'upcoming'])->orderBy('date_start')->get();
        $past = Tournament::with('translations')->where('status', 'past')->orderBy('date_start', 'desc')->get();
        $news = News::with('translations')->published()->orderBy('published_at', 'desc')->limit(20)->get();

        $line = fn ($tour) => '- [' . $t($tour) . '](' . url('/turnyrai/' . rawurlencode($tour->slug)) . '): '
            . $tour->date_start?->format('Y-m-d') . ($tour->date_end && ! $tour->date_end->isSameDay($tour->date_start) ? ' – ' . $tour->date_end->format('Y-m-d') : '')
            . ($tour->location ? ', ' . $tour->location : '')
            . ($tour->tournated_id && $tour->show_schedule ? '. Grafikas ir rezultatai: ' . route('schedule.show', $tour->tournated_id) : '');

        $out = "# Padelio turnyrai (padelioturnyrai.lt)

"
            . "> Lietuvos padelio turnyrų organizatorių svetainė: būsimi ir įvykę turnyrai, registracija, gyvi mačų grafikai, rezultatai, grupės ir tinkleliai, naujienos ir rėmėjai. Turnyrai daugiausia vyksta Klaipėdoje. Kalbos: lietuvių (pagrindinė) ir anglų (/en).

"
            . "## Pagrindiniai puslapiai

"
            . '- [Pradžia](' . url('/') . "): artimiausias turnyras, statistika, įvykę turnyrai, rėmėjai
"
            . '- [Turnyrai](' . url('/turnyrai') . "): visi turnyrai su datomis, vieta ir registracija
"
            . '- [Naujienos](' . url('/naujienos') . ")
"
            . '- [Kontaktai](' . url('/kontaktai') . ")
"
            . '- [Tapk rėmėju](' . url('/tapk-remeju') . "): rėmimo paketai

";
        if ($upcoming->isNotEmpty()) {
            $out .= "## Artėjantys turnyrai

" . $upcoming->map($line)->implode("
") . "

";
        }
        if ($past->isNotEmpty()) {
            $out .= "## Įvykę turnyrai

" . $past->map($line)->implode("
") . "

";
        }
        if ($news->isNotEmpty()) {
            $out .= "## Naujienos

" . $news->map(fn ($n) => '- [' . ($n->translation('lt')?->title ?? $n->slug) . '](' . url('/naujienos/' . rawurlencode($n->slug)) . ')' . ($n->published_at ? ': ' . $n->published_at->format('Y-m-d') : ''))->implode("
") . "

";
        }
        $out .= "## Kita

- [Svetainės žemėlapis](" . url('/sitemap.xml') . ")
- Mobilioji programėlė „Padelio turnyrai“: App Store https://apps.apple.com/app/id6833871129
";

        return response($out, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
