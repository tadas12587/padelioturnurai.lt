<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SeoSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'SEO ir svetainė';
    protected static ?string $title           = 'SEO ir svetainės nustatymai';
    protected static ?int    $navigationSort  = 98;
    protected static string  $view            = 'filament.pages.seo-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'seo_description_lt' => Setting::get('seo_description_lt', ''),
            'seo_description_en' => Setting::get('seo_description_en', ''),
            'social_facebook'    => Setting::get('social_facebook', ''),
            'social_instagram'   => Setting::get('social_instagram', ''),
            'contact_email'      => Setting::get('contact_email', ''),
            'contact_phone'      => Setting::get('contact_phone', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Meta aprašymai')
                    ->description('Rodomi Google paieškos rezultatuose po puslapio pavadinimu. Optimalus ilgis — 50–160 simbolių.')
                    ->schema([
                        Textarea::make('seo_description_lt')
                            ->label('Aprašymas (LT)')
                            ->rows(3)
                            ->maxLength(200)
                            ->helperText(fn ($state) => mb_strlen($state ?? '') . ' / 160 simbolių')
                            ->live(debounce: 500)
                            ->placeholder('Lietuvos padelio turnyrai – registracija, rezultatai, reitingai ir naujienos viename puslapyje.'),

                        Textarea::make('seo_description_en')
                            ->label('Aprašymas (EN)')
                            ->rows(3)
                            ->maxLength(200)
                            ->helperText(fn ($state) => mb_strlen($state ?? '') . ' / 160 simbolių')
                            ->live(debounce: 500)
                            ->placeholder('Lithuanian padel tournaments – registration, results, rankings and news in one place.'),
                    ]),

                Section::make('Socialiniai tinklai')
                    ->description('Nuorodos rodomos svetainės apačioje (footer). Palikus tuščią — ikona nerodoma.')
                    ->schema([
                        TextInput::make('social_facebook')
                            ->label('Facebook nuoroda')
                            ->url()
                            ->placeholder('https://www.facebook.com/jusupuslapis'),

                        TextInput::make('social_instagram')
                            ->label('Instagram nuoroda')
                            ->url()
                            ->placeholder('https://www.instagram.com/jusupaskyra'),
                    ])
                    ->columns(2),

                Section::make('Kontaktinė informacija')
                    ->description('Rodoma /kontaktai puslapyje po forma. Palikus tuščią — blokas nerodomas.')
                    ->schema([
                        TextInput::make('contact_email')
                            ->label('El. paštas')
                            ->email()
                            ->placeholder('info@padelioturnyrai.lt'),

                        TextInput::make('contact_phone')
                            ->label('Telefonas')
                            ->placeholder('+370 600 00000'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        Notification::make()
            ->title('Nustatymai išsaugoti!')
            ->success()
            ->send();
    }

    /**
     * Resolve the real web root (on shared hosting public/ is copied to a separate web/ dir).
     */
    private function webrootPath(string $file): string
    {
        $storagePath = env('PUBLIC_STORAGE_PATH');
        $root = $storagePath ? dirname(rtrim($storagePath, '/')) : public_path();

        return rtrim($root, '/') . '/' . ltrim($file, '/');
    }

    /**
     * SEO health checks shown below the form.
     *
     * @return array{score:int, checks:array<int,array{label:string,status:string,note:string}>}
     */
    public function getSeoChecks(): array
    {
        $checks = [];

        // 1. APP_URL
        $appUrl = config('app.url', '');
        if (str_starts_with($appUrl, 'https://') && !str_contains($appUrl, 'localhost')) {
            $checks[] = ['label' => 'Svetainės adresas (APP_URL)', 'status' => 'ok', 'note' => $appUrl];
        } else {
            $checks[] = ['label' => 'Svetainės adresas (APP_URL)', 'status' => 'fail', 'note' => 'Nustatykite APP_URL=https://... .env faile — be jo sitemap ir dalinimosi nuorodos bus neteisingos.'];
        }

        // 2. Meta description LT
        $descLt = Setting::get('seo_description_lt', '');
        $len = mb_strlen($descLt);
        if ($len >= 50 && $len <= 160) {
            $checks[] = ['label' => 'Meta aprašymas (LT)', 'status' => 'ok', 'note' => $len . ' simbolių — optimalu'];
        } elseif ($len > 0) {
            $checks[] = ['label' => 'Meta aprašymas (LT)', 'status' => 'warn', 'note' => $len . ' simbolių — rekomenduojama 50–160'];
        } else {
            $checks[] = ['label' => 'Meta aprašymas (LT)', 'status' => 'warn', 'note' => 'Neįvestas — naudojamas standartinis tekstas. Įveskite aukščiau.'];
        }

        // 3. Meta description EN
        $descEn = Setting::get('seo_description_en', '');
        if (mb_strlen($descEn) > 0) {
            $checks[] = ['label' => 'Meta aprašymas (EN)', 'status' => 'ok', 'note' => mb_strlen($descEn) . ' simbolių'];
        } else {
            $checks[] = ['label' => 'Meta aprašymas (EN)', 'status' => 'warn', 'note' => 'Neįvestas — EN versijai naudojamas LT aprašymas.'];
        }

        // 4. Sitemap
        $checks[] = ['label' => 'Sitemap.xml', 'status' => 'ok', 'note' => url('/sitemap.xml') . ' — generuojamas automatiškai iš turnyrų ir naujienų'];

        // 5. robots.txt
        $robotsPath = $this->webrootPath('robots.txt');
        if (is_file($robotsPath)) {
            $robots = (string) file_get_contents($robotsPath);
            if (str_contains($robots, 'Sitemap:')) {
                $checks[] = ['label' => 'robots.txt', 'status' => 'ok', 'note' => 'Yra ir nurodo sitemap'];
            } else {
                $checks[] = ['label' => 'robots.txt', 'status' => 'warn', 'note' => 'Yra, bet be "Sitemap:" eilutės — atnaujinkite failą serveryje (web/robots.txt)'];
            }
        } else {
            $checks[] = ['label' => 'robots.txt', 'status' => 'fail', 'note' => 'Nerastas (' . $robotsPath . ')'];
        }

        // 6. Favicon
        $faviconPath = $this->webrootPath('favicon.ico');
        if (is_file($faviconPath) && filesize($faviconPath) > 0) {
            $checks[] = ['label' => 'Favicon', 'status' => 'ok', 'note' => 'Yra'];
        } else {
            $checks[] = ['label' => 'Favicon', 'status' => 'warn', 'note' => 'Tuščias arba nerastas — naršyklės kortelėje nebus ikonos'];
        }

        // 7. Social links
        if (Setting::get('social_facebook') || Setting::get('social_instagram')) {
            $checks[] = ['label' => 'Socialiniai tinklai', 'status' => 'ok', 'note' => 'Nuorodos įvestos, rodomos footer'];
        } else {
            $checks[] = ['label' => 'Socialiniai tinklai', 'status' => 'warn', 'note' => 'Neįvesta — footer socialinės ikonos nerodomos'];
        }

        // 8. Contact info
        if (Setting::get('contact_email') || Setting::get('contact_phone')) {
            $checks[] = ['label' => 'Kontaktinė informacija', 'status' => 'ok', 'note' => 'Rodoma /kontaktai puslapyje'];
        } else {
            $checks[] = ['label' => 'Kontaktinė informacija', 'status' => 'warn', 'note' => 'Neįvesta — /kontaktai puslapyje rodoma tik forma'];
        }

        // 9. Hreflang (static — implemented in layout)
        $checks[] = ['label' => 'Hreflang (LT/EN)', 'status' => 'ok', 'note' => 'Kalbų žymos įdiegtos visiems puslapiams'];

        // 10. Structured data (static — implemented in views)
        $checks[] = ['label' => 'Struktūrizuoti duomenys (JSON-LD)', 'status' => 'ok', 'note' => 'Turnyrai (SportsEvent), naujienos (NewsArticle), organizacija'];

        // 11. Hero photos (home og:image)
        $heroDir = rtrim(env('PUBLIC_STORAGE_PATH', public_path('storage')), '/') . '/herofoto';
        $heroPhotos = glob($heroDir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE) ?: [];
        if (count($heroPhotos) > 0) {
            $checks[] = ['label' => 'Dalinimosi nuotrauka (pagrindinis)', 'status' => 'ok', 'note' => count($heroPhotos) . ' herofoto nuotraukos'];
        } else {
            $checks[] = ['label' => 'Dalinimosi nuotrauka (pagrindinis)', 'status' => 'warn', 'note' => 'Nėra herofoto nuotraukų — dalinantis pagrindiniu puslapiu nebus paveikslėlio'];
        }

        $okCount = count(array_filter($checks, fn ($c) => $c['status'] === 'ok'));
        $score = (int) round($okCount / count($checks) * 100);

        return ['score' => $score, 'checks' => $checks];
    }
}
