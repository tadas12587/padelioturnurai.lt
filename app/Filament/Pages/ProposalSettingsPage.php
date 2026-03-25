<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\Tournament;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ProposalSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-star';
    protected static ?string $navigationLabel = 'Tapk rėmėju — nustatymai';
    protected static ?string $title           = 'Tapk rėmėju — puslapio nustatymai';
    protected static ?int    $navigationSort  = 50;
    protected static string  $view            = 'filament.pages.proposal-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $photos = Setting::get('proposal_photos', '[]');
        if (is_string($photos)) {
            $photos = json_decode($photos, true) ?? [];
        }

        $streamPhoto = Setting::get('proposal_stream_photo');

        $this->form->fill([
            // ── General ───────────────────────────────────────────────────
            'proposal_active'            => (bool) Setting::get('proposal_active', '1'),
            'proposal_tournament_id'     => Setting::get('proposal_tournament_id'),

            // ── Hero (LT / EN) ────────────────────────────────────────────
            'proposal_headline'          => Setting::get('proposal_headline', 'Didžiausias padelio turnyras Lietuvoje'),
            'proposal_headline_en'       => Setting::get('proposal_headline_en', ''),
            'proposal_subheadline'       => Setting::get('proposal_subheadline', '300+ žaidėjų · 10 000+ žiūrovų · TOP vasaros sporto renginys'),
            'proposal_subheadline_en'    => Setting::get('proposal_subheadline_en', ''),
            'proposal_deadline'          => Setting::get('proposal_deadline', ''),
            'proposal_urgency_text'      => Setting::get('proposal_urgency_text', ''),
            'proposal_urgency_text_en'   => Setting::get('proposal_urgency_text_en', ''),

            // ── ROI stats ─────────────────────────────────────────────────
            'stat_participants'          => Setting::get('stat_participants', ''),
            'stat_viewers'               => Setting::get('stat_viewers', ''),
            'stat_social_reach'          => Setting::get('stat_social_reach', ''),
            'stat_video_views'           => Setting::get('stat_video_views', ''),
            'stat_partners'              => Setting::get('stat_partners', ''),
            'stat_media'                 => Setting::get('stat_media', ''),

            // ── Audience (LT / EN) ────────────────────────────────────────
            'proposal_audience'          => Setting::get('proposal_audience', "✔ Aktyvūs, sportuojantys žmonės\n✔ Aukštesnių pajamų grupė\n✔ Perkantys lifestyle produktus\n✔ Verslo savininkai ir vadovai\n✔ 25–50 metų amžiaus grupė"),
            'proposal_audience_en'       => Setting::get('proposal_audience_en', ''),

            // ── Case study (LT / EN) ──────────────────────────────────────
            'proposal_case_study'        => Setting::get('proposal_case_study', ''),
            'proposal_case_study_en'     => Setting::get('proposal_case_study_en', ''),

            // ── Streaming section (LT / EN) ───────────────────────────────
            'proposal_stream_title_lt'      => Setting::get('proposal_stream_title_lt', 'Pasiekiame žiūrovus per tiesioginę transliaciją'),
            'proposal_stream_title_en'      => Setting::get('proposal_stream_title_en', 'Reaching viewers through live streaming'),
            'proposal_stream_text_lt'       => Setting::get('proposal_stream_text_lt', ''),
            'proposal_stream_text_en'       => Setting::get('proposal_stream_text_en', ''),
            'proposal_stream_photo'         => $streamPhoto ? [$streamPhoto] : [],
            'proposal_stream_url'           => Setting::get('proposal_stream_url', ''),
            'proposal_stream_url_label_lt'  => Setting::get('proposal_stream_url_label_lt', 'Žiūrėti transliaciją'),
            'proposal_stream_url_label_en'  => Setting::get('proposal_stream_url_label_en', 'Watch stream'),
            // Streaming stats (4 rows: value + LT label + EN label)
            'proposal_stream_stat1_value'     => Setting::get('proposal_stream_stat1_value', ''),
            'proposal_stream_stat1_label_lt'  => Setting::get('proposal_stream_stat1_label_lt', 'Transliacijos kanalų'),
            'proposal_stream_stat1_label_en'  => Setting::get('proposal_stream_stat1_label_en', 'Streaming channels'),
            'proposal_stream_stat2_value'     => Setting::get('proposal_stream_stat2_value', ''),
            'proposal_stream_stat2_label_lt'  => Setting::get('proposal_stream_stat2_label_lt', 'Live žiūrovų'),
            'proposal_stream_stat2_label_en'  => Setting::get('proposal_stream_stat2_label_en', 'Live viewers'),
            'proposal_stream_stat3_value'     => Setting::get('proposal_stream_stat3_value', ''),
            'proposal_stream_stat3_label_lt'  => Setting::get('proposal_stream_stat3_label_lt', 'Peržiūrų vėliau'),
            'proposal_stream_stat3_label_en'  => Setting::get('proposal_stream_stat3_label_en', 'Views after event'),
            'proposal_stream_stat4_value'     => Setting::get('proposal_stream_stat4_value', ''),
            'proposal_stream_stat4_label_lt'  => Setting::get('proposal_stream_stat4_label_lt', ''),
            'proposal_stream_stat4_label_en'  => Setting::get('proposal_stream_stat4_label_en', ''),

            // ── Value anchor (LT / EN) ────────────────────────────────────
            'proposal_value_anchor'      => Setting::get('proposal_value_anchor', 'Investicijos į reklamą panašiuose renginiuose įprastai: 3 000 – 15 000 €'),
            'proposal_value_anchor_en'   => Setting::get('proposal_value_anchor_en', ''),

            // ── Contact ───────────────────────────────────────────────────
            'proposal_contact_name'      => Setting::get('proposal_contact_name', ''),
            'proposal_contact_email'     => Setting::get('proposal_contact_email', ''),
            'proposal_contact_phone'     => Setting::get('proposal_contact_phone', ''),

            // ── Photos ────────────────────────────────────────────────────
            'proposal_photos'            => $photos,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                // ══ VISIBILITY ════════════════════════════════════════════
                Section::make('Puslapis')
                    ->schema([
                        Toggle::make('proposal_active')
                            ->label('Puslapis matomas viešai')
                            ->helperText('Išjunkite jei puslapis dar nepasirengęs.'),

                        Select::make('proposal_tournament_id')
                            ->label('Turnyras, kuriam ieškome rėmėjų')
                            ->options(
                                Tournament::with('translations')
                                    ->orderBy('date_start', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn ($t) => [
                                        $t->id => ($t->translation('lt')?->title ?? $t->slug)
                                               . ' (' . $t->date_start->format('Y') . ')',
                                    ])
                            )
                            ->searchable()
                            ->nullable(),
                    ])
                    ->columns(2),

                // ══ HERO TEXTS ════════════════════════════════════════════
                Section::make('Pagrindiniai tekstai')
                    ->description('🇱🇹 kairėje — lietuviškai, 🇬🇧 dešinėje — angliškai')
                    ->schema([
                        TextInput::make('proposal_headline')
                            ->label('🇱🇹 Šūkis')
                            ->placeholder('Didžiausias padelio turnyras Lietuvoje'),
                        TextInput::make('proposal_headline_en')
                            ->label('🇬🇧 Headline')
                            ->placeholder('The biggest padel tournament in Lithuania'),

                        TextInput::make('proposal_subheadline')
                            ->label('🇱🇹 Subheadline')
                            ->placeholder('300+ žaidėjų · 10 000+ žiūrovų'),
                        TextInput::make('proposal_subheadline_en')
                            ->label('🇬🇧 Subheadline')
                            ->placeholder('300+ players · 10 000+ viewers'),

                        TextInput::make('proposal_deadline')
                            ->label('Registracijos terminas (bendra)')
                            ->placeholder('2025 m. birželio 1 d.'),

                        TextInput::make('proposal_urgency_text')
                            ->label('🇱🇹 Urgency'),
                        TextInput::make('proposal_urgency_text_en')
                            ->label('🇬🇧 Urgency')
                            ->placeholder('Only 3 title sponsor slots left'),
                    ])
                    ->columns(2),

                // ══ ROI STATS ═════════════════════════════════════════════
                Section::make('Praeitų metų rezultatai (ROI)')
                    ->description('Skaičiai — universalūs, etiketės automatiškai verčiamos.')
                    ->schema([
                        TextInput::make('stat_participants')->label('Dalyviai')->placeholder('290'),
                        TextInput::make('stat_viewers')->label('Žiūrovai')->placeholder('14 000+'),
                        TextInput::make('stat_social_reach')->label('Social reach')->placeholder('45 000+'),
                        TextInput::make('stat_video_views')->label('Video peržiūros')->placeholder('8 500+'),
                        TextInput::make('stat_partners')->label('Partnerių')->placeholder('12'),
                        TextInput::make('stat_media')->label('Media publikacijos')->placeholder('6'),
                    ])
                    ->columns(3),

                // ══ AUDIENCE ══════════════════════════════════════════════
                Section::make('Tikslinė auditorija')
                    ->description('Kiekviena eilutė — atskiras punktas (su ✔ ar be)')
                    ->schema([
                        Textarea::make('proposal_audience')
                            ->label('🇱🇹 Auditorija')
                            ->rows(5)
                            ->placeholder("✔ Aktyvūs, sportuojantys žmonės\n✔ Aukštesnių pajamų grupė"),
                        Textarea::make('proposal_audience_en')
                            ->label('🇬🇧 Audience')
                            ->rows(5)
                            ->placeholder("✔ Active, sports-oriented people\n✔ Higher income group"),
                    ])
                    ->columns(2),

                // ══ CASE STUDY ════════════════════════════════════════════
                Section::make('Praeitų metų partneriai (Case study)')
                    ->schema([
                        Textarea::make('proposal_case_study')
                            ->label('🇱🇹 Tekstas')
                            ->rows(4)
                            ->placeholder("Praeitais metais renginyje dalyvavo Twinbet, Škoda ir kiti..."),
                        Textarea::make('proposal_case_study_en')
                            ->label('🇬🇧 Text')
                            ->rows(4)
                            ->placeholder("Last year the event was supported by Twinbet, Škoda and others..."),
                    ])
                    ->columns(2),

                // ══ STREAMING SECTION ═════════════════════════════════════
                Section::make('📡 Gyvos transliacijos')
                    ->description('Sekcija rodoma tarp „Partneriai" ir „Paketai". Nuotrauka, statistika ir nuoroda.')
                    ->schema([

                        // Title LT / EN
                        TextInput::make('proposal_stream_title_lt')
                            ->label('🇱🇹 Sekcijos antraštė')
                            ->placeholder('Pasiekiame žiūrovus per tiesioginę transliaciją'),
                        TextInput::make('proposal_stream_title_en')
                            ->label('🇬🇧 Section title')
                            ->placeholder('Reaching viewers through live streaming'),

                        // Text LT / EN
                        Textarea::make('proposal_stream_text_lt')
                            ->label('🇱🇹 Tekstas')
                            ->rows(4)
                            ->placeholder("Mūsų turnyrai transliuojami per kelis kanalus ir pasiekia tūkstančius žiūrovų..."),
                        Textarea::make('proposal_stream_text_en')
                            ->label('🇬🇧 Text')
                            ->rows(4)
                            ->placeholder("Our tournaments are streamed across multiple channels..."),

                        // Photo + URL
                        FileUpload::make('proposal_stream_photo')
                            ->label('Nuotrauka')
                            ->image()
                            ->disk('public')
                            ->directory('proposal')
                            ->helperText('Rodoma šalia teksto. Rekomenduojamas santykis 4:3.'),

                        Section::make('Nuoroda')
                            ->schema([
                                TextInput::make('proposal_stream_url')
                                    ->label('Nuorodos URL')
                                    ->url()
                                    ->placeholder('https://youtube.com/...')
                                    ->helperText('Jei tuščia — mygtukas nematomas.'),
                                TextInput::make('proposal_stream_url_label_lt')
                                    ->label('🇱🇹 Mygtuko tekstas')
                                    ->placeholder('Žiūrėti transliaciją'),
                                TextInput::make('proposal_stream_url_label_en')
                                    ->label('🇬🇧 Button label')
                                    ->placeholder('Watch stream'),
                            ])
                            ->columns(3),

                        // Streaming stats (4 rows)
                        Placeholder::make('stream_stats_heading')
                            ->label('')
                            ->content('📊 Statistika (iki 4 eilučių — skaičius + etiketė LT ir EN):')
                            ->columnSpanFull(),

                        TextInput::make('proposal_stream_stat1_value')->label('1 — Skaičius')->placeholder('3'),
                        TextInput::make('proposal_stream_stat1_label_lt')->label('1 — 🇱🇹 Etiketė')->placeholder('Transliacijos kanalų'),
                        TextInput::make('proposal_stream_stat1_label_en')->label('1 — 🇬🇧 Label')->placeholder('Streaming channels'),

                        TextInput::make('proposal_stream_stat2_value')->label('2 — Skaičius')->placeholder('2 400+'),
                        TextInput::make('proposal_stream_stat2_label_lt')->label('2 — 🇱🇹 Etiketė')->placeholder('Live žiūrovų'),
                        TextInput::make('proposal_stream_stat2_label_en')->label('2 — 🇬🇧 Label')->placeholder('Live viewers'),

                        TextInput::make('proposal_stream_stat3_value')->label('3 — Skaičius')->placeholder('8 500+'),
                        TextInput::make('proposal_stream_stat3_label_lt')->label('3 — 🇱🇹 Etiketė')->placeholder('Peržiūrų vėliau'),
                        TextInput::make('proposal_stream_stat3_label_en')->label('3 — 🇬🇧 Label')->placeholder('Views after event'),

                        TextInput::make('proposal_stream_stat4_value')->label('4 — Skaičius')->placeholder(''),
                        TextInput::make('proposal_stream_stat4_label_lt')->label('4 — 🇱🇹 Etiketė')->placeholder(''),
                        TextInput::make('proposal_stream_stat4_label_en')->label('4 — 🇬🇧 Label')->placeholder(''),
                    ])
                    ->columns(3),

                // ══ VALUE ANCHOR ══════════════════════════════════════════
                Section::make('Value anchoring (prieš paketus)')
                    ->schema([
                        TextInput::make('proposal_value_anchor')
                            ->label('🇱🇹 Palyginimo frazė')
                            ->placeholder('Investicijos į reklamą panašiuose renginiuose įprastai: 3 000 – 15 000 €')
                            ->helperText('Rodoma virš paketų.'),
                        TextInput::make('proposal_value_anchor_en')
                            ->label('🇬🇧 Comparison phrase')
                            ->placeholder('Typical ad spend at similar events: €3,000 – €15,000'),
                    ])
                    ->columns(2),

                // ══ CONTACT ═══════════════════════════════════════════════
                Section::make('Kontaktinė informacija')
                    ->schema([
                        TextInput::make('proposal_contact_name')->label('Vardas'),
                        TextInput::make('proposal_contact_email')->label('El. paštas')->email(),
                        TextInput::make('proposal_contact_phone')->label('Telefonas')->placeholder('+370 600 00000'),
                    ])
                    ->columns(3),

                // ══ PHOTOS ════════════════════════════════════════════════
                Section::make('Nuotraukos (bendra galerija)')
                    ->description('1-oji → hero fonas. 2-oji → auditorijos sekcija. 3-oji → case study. Likusios → galerijos juosta.')
                    ->schema([
                        FileUpload::make('proposal_photos')
                            ->label('Nuotraukos')
                            ->multiple()
                            ->image()
                            ->disk('public')
                            ->directory('proposal')
                            ->reorderable()
                            ->maxFiles(30)
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            if ($key === 'proposal_photos') {
                Setting::set($key, json_encode(array_values((array) ($value ?? []))));
            } elseif ($key === 'proposal_stream_photo') {
                // FileUpload returns array even for single — take first item
                $arr = array_values(array_filter((array) ($value ?? [])));
                Setting::set($key, $arr[0] ?? '');
            } else {
                Setting::set($key, $value ?? '');
            }
        }

        Notification::make()->title('Išsaugota!')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPage')
                ->label('Peržiūrėti puslapį')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(route('proposal'))
                ->openUrlInNewTab(),
        ];
    }
}
