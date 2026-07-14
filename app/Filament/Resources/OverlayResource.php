<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OverlayResource\Pages;
use App\Models\Overlay;
use App\Services\OverlayData;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class OverlayResource extends Resource
{
    protected static ?string $model = Overlay::class;

    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static ?string $navigationLabel = 'Overlay\'ai';

    protected static ?string $navigationGroup = 'Transliacijos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Pavadinimas')
                ->required(),

            TextInput::make('tournament_external_id')
                ->label('Tournated turnyro ID')
                ->helperText('Pvz. 10424')
                ->live(),

            Section::make('Išvaizda')
                ->schema([
                    Select::make('config.theme')
                        ->label('Spalvų tema')
                        ->options(collect(Overlay::themePresets())->map(fn ($p) => $p['label'])->all())
                        ->default('gold_night')
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            $colors = Overlay::themePresets()[$state]['colors'] ?? null;
                            if ($colors) {
                                $set('config.colors.bg', $colors['bg']);
                                $set('config.colors.text', $colors['text']);
                                $set('config.colors.accent', $colors['accent']);
                                $set('config.colors.muted', $colors['muted']);
                            }
                        }),

                    ColorPicker::make('config.colors.bg')->label('Fonas'),
                    ColorPicker::make('config.colors.text')->label('Tekstas'),
                    ColorPicker::make('config.colors.accent')->label('Akcentas'),
                    ColorPicker::make('config.colors.muted')->label('Antrinė'),

                    FileUpload::make('config.logo')->label('Logotipas')->image()->directory('overlay-logos'),

                    Select::make('config.position')
                        ->label('Pozicija ekrane')
                        ->options([
                            'top-left'      => 'Viršus — kairė',
                            'top-center'    => 'Viršus — centras',
                            'top-right'     => 'Viršus — dešinė',
                            'mid-left'      => 'Vidurys — kairė',
                            'center'        => 'Centras',
                            'mid-right'     => 'Vidurys — dešinė',
                            'bottom-left'   => 'Apačia — kairė',
                            'bottom-center' => 'Apačia — centras',
                            'bottom-right'  => 'Apačia — dešinė',
                        ])
                        ->default('bottom-left'),

                    CheckboxList::make('config.visible_columns')
                        ->label('Rodomi stulpeliai')
                        ->options([
                            'place'  => 'Vieta',
                            'name'   => 'Pora',
                            'points' => 'Taškai',
                            'wins'   => 'Laimėta',
                            'losses' => 'Pralaimėta',
                            'played' => 'Sužaista',
                        ])
                        ->columns(2),
                ])
                ->columns(2),

            Section::make('Langai')
                ->description('Sukurk langus (scenas). Valdymo puslapyje juos įjungsi/išjungsi Play/Stop.')
                ->schema([
                    Repeater::make('windows')
                        ->label('Langai')
                        ->schema([
                            Hidden::make('id')->default(fn () => 'w' . Str::random(6)),

                            TextInput::make('name')->label('Lango pavadinimas')->required(),

                            Select::make('type')
                                ->label('Tipas')
                                ->options(['groups' => 'Grupės', 'bracket' => 'Brackets', 'draw' => 'Traukimas', 'h2h' => 'Akistata', 'score' => 'Rezultatas', 'sponsors' => 'Rėmėjai', 'photowall' => 'Foto sienelė', 'schedule' => 'Tvarkaraštis'])
                                ->default('groups')
                                ->live(),

                            Toggle::make('scrim_enabled')
                                ->label('Tamsinti foną')
                                ->live()
                                ->default(false),
                            TextInput::make('scrim_opacity')
                                ->label('Fono tamsumas %')
                                ->numeric()->minValue(0)->maxValue(100)->default(55)
                                ->visible(fn (Forms\Get $get) => (bool) $get('scrim_enabled')),

                            Repeater::make('subgroups')
                                ->label('Pogrupiai')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'groups')
                                ->schema([
                                    Select::make('category_id')
                                        ->label('Kategorija')
                                        ->live()
                                        ->options(function ($livewire) {
                                            $tid = data_get($livewire, 'data.tournament_external_id');
                                            if (! $tid) {
                                                return [];
                                            }
                                            $stages = app(OverlayData::class)->categoryStages((string) $tid);
                                            $out = [];
                                            foreach (app(OverlayData::class)->categories((string) $tid) as $c) {
                                                $st = $stages[(string) $c['id']] ?? [];
                                                // Groups window → only categories that actually have groups.
                                                if (! ($st['has_groups'] ?? false)) {
                                                    continue;
                                                }
                                                $out[$c['id']] = $c['category']['name'] ?? ('#' . $c['id']);
                                            }
                                            return $out;
                                        }),

                                    Select::make('segments')
                                        ->label('Segmentai')
                                        ->helperText('Pvz. Main, 5-8, 9-16. Gali pažymėti kelis. Tuščia = visi.')
                                        ->multiple()
                                        ->live()
                                        ->placeholder('Visi segmentai')
                                        ->options(function ($livewire, Forms\Get $get) {
                                            $tid = data_get($livewire, 'data.tournament_external_id');
                                            $cid = $get('category_id');
                                            if (! $tid || ! $cid) {
                                                return [];
                                            }
                                            return app(OverlayData::class)->segments((string) $tid, (int) $cid);
                                        }),

                                    Select::make('group_id')
                                        ->label('Pogrupis')
                                        ->placeholder('Visi pogrupiai')
                                        ->options(function ($livewire, Forms\Get $get) {
                                            $tid = data_get($livewire, 'data.tournament_external_id');
                                            $cid = $get('category_id');
                                            if (! $tid || ! $cid) {
                                                return ['' => 'Visi pogrupiai'];
                                            }
                                            $segs = array_map('strval', $get('segments') ?? []);
                                            $out = ['' => 'Visi pogrupiai'];
                                            foreach (app(OverlayData::class)->groups((string) $tid, (int) $cid) as $g) {
                                                if ($segs && ! in_array((string) ($g['segment'] ?? ''), $segs, true)) {
                                                    continue;
                                                }
                                                $out[$g['id']] = $g['name'] ?? ('#' . $g['id']);
                                            }
                                            return $out;
                                        }),
                                ])
                                ->columns(2)
                                ->defaultItems(1),

                            Select::make('category_id')
                                ->label('Kategorija (bracketas)')
                                ->live()
                                ->options(function ($livewire) {
                                    $tid = data_get($livewire, 'data.tournament_external_id');
                                    if (! $tid) {
                                        return [];
                                    }
                                    $stages = app(OverlayData::class)->categoryStages((string) $tid);
                                    $out = [];
                                    foreach (app(OverlayData::class)->categories((string) $tid) as $c) {
                                        if ($stages[(string) $c['id']]['has_bracket'] ?? false) {
                                            $out[$c['id']] = $c['category']['name'] ?? ('#' . $c['id']);
                                        }
                                    }
                                    return $out;
                                })
                                ->helperText('Bracketas užsipildo automatiškai iš turnyro tinklelio.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'bracket'),

                            Select::make('segments')
                                ->label('Segmentai (bracketai)')
                                ->helperText('Pvz. pagrindinis tinklelis, „dėl 3 vietos". Gali pažymėti kelis. Tuščia = visi.')
                                ->multiple()
                                ->options(function ($livewire, Forms\Get $get) {
                                    $tid = data_get($livewire, 'data.tournament_external_id');
                                    $cid = $get('category_id');
                                    if (! $tid || ! $cid) {
                                        return [];
                                    }
                                    return app(OverlayData::class)->bracketSegments((string) $tid, (int) $cid);
                                })
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'bracket'),

                            Select::make('schedule_variant')
                                ->label('Variantas')
                                ->options([
                                    'by_court' => 'Pagal kortą',
                                    'by_time'  => 'Pagal laiką',
                                    'now'      => 'Dabar žaidžiama',
                                    'next'     => 'Toliau aikštelėje',
                                    'results'  => 'Rezultatų juosta',
                                ])
                                ->default('by_court')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            DatePicker::make('date')
                                ->label('Data')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            Select::make('category_ids')
                                ->label('Kategorijos')
                                ->placeholder('Visos kategorijos')
                                ->multiple()
                                ->options(function ($livewire) {
                                    $tid = data_get($livewire, 'data.tournament_external_id');
                                    if (! $tid) {
                                        return [];
                                    }
                                    $out = [];
                                    foreach (app(OverlayData::class)->categories((string) $tid) as $c) {
                                        $out[$c['id']] = $c['category']['name'] ?? ('#' . $c['id']);
                                    }
                                    return $out;
                                })
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            Select::make('courts')
                                ->label('Kortai')
                                ->placeholder('Visi kortai')
                                ->multiple()
                                ->options(function ($livewire) {
                                    $tid = data_get($livewire, 'data.tournament_external_id');
                                    return $tid ? app(OverlayData::class)->courts((string) $tid) : [];
                                })
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            TextInput::make('limit')
                                ->label('Kiek rodyti (Dabar / Toliau)')
                                ->numeric()->minValue(1)->default(6)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'schedule'),

                            Select::make('category_id')
                                ->label('Kategorija (traukimui)')
                                ->live()
                                ->options(function ($livewire) {
                                    $tid = data_get($livewire, 'data.tournament_external_id');
                                    if (! $tid) {
                                        return [];
                                    }
                                    $out = [];
                                    foreach (app(OverlayData::class)->categories((string) $tid) as $c) {
                                        $out[$c['id']] = $c['category']['name'] ?? ('#' . $c['id']);
                                    }
                                    return $out;
                                })
                                ->helperText('Iš čia užkrausi dalyvius valdymo puslapyje.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),

                            Select::make('format')
                                ->label('Formatas')
                                ->options(['groups' => 'Grupių lentelės', 'bracket' => 'Bracket (sėklavimas)'])
                                ->default('groups')->live()
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),

                            TextInput::make('group_count')->label('Grupių skaičius')->numeric()->minValue(1)->default(4)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw' && ($get('format') ?? 'groups') === 'groups'),
                            TextInput::make('group_size')->label('Komandų grupėje')->numeric()->minValue(2)->default(4)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw' && ($get('format') ?? 'groups') === 'groups'),
                            Select::make('bracket_size')->label('Bracket dydis')->options([8 => 8, 16 => 16, 32 => 32])->default(16)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw' && ($get('format') ?? 'groups') === 'bracket'),

                            Toggle::make('use_pots')->label('Naudoti krepšelius / sėklas')->default(true)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
                            Select::make('camera_corner')->label('Kameros kampas (skaidrus)')
                                ->options(['bottom-right' => 'Apačia — dešinė', 'bottom-left' => 'Apačia — kairė', 'top-right' => 'Viršus — dešinė', 'top-left' => 'Viršus — kairė'])
                                ->default('bottom-right')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
                            Toggle::make('show_tournament')->label('Rodyti turnyro logo + pavadinimą')->default(true)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
                            Select::make('sponsor_ids')->label('Rėmėjai iš sąrašo')->multiple()
                                ->options(fn () => \App\Models\Sponsor::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
                            Select::make('gallery_ids')->label('Galerija (rėmėjai)')->multiple()
                                ->options(fn () => \App\Models\Gallery::orderBy('name')->pluck('name', 'id'))
                                ->helperText('Pasirink galeriją — jos nuotraukos rodomos automatiškai.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
                            FileUpload::make('images')->label('Arba įkelk rėmėjų logotipus')
                                ->image()->multiple()->reorderable()->disk('public')->directory('overlay-sponsors')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
                            TextInput::make('rotate_seconds')->label('Rėmėjų slinkimo greitis (s/logo)')
                                ->numeric()->default(5)->minValue(2)
                                ->helperText('Juosta slenka po vieną logo; kuo didesnis skaičius, tuo lėčiau.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),

                            CheckboxList::make('h2h_center')
                                ->label('Ką rodyti centre')
                                ->options(['time' => 'Rungtynių laikas', 'score' => 'Live rezultatas', 'court' => 'Kortas / etapas', 'vs' => 'VS / tekstas'])
                                ->default(['time', 'score', 'court', 'vs'])->columns(2)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            TextInput::make('h2h_text')->label('Centro tekstas (kai „VS / tekstas")')->default('VS')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            Toggle::make('h2h_animate')->label('Lėta animacija (zoom link žiūrovo)')->default(true)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            TextInput::make('h2h_size')->label('Nuotraukų aukštis (% ekrano)')
                                ->numeric()->default(96)->minValue(40)->maxValue(120)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            TextInput::make('h2h_edge')->label('Atstumas nuo kraštų (vw)')
                                ->numeric()->default(0)->minValue(0)->maxValue(30)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            TextInput::make('h2h_overlap')->label('Persidengimas: komandos draugai (vw)')
                                ->numeric()->default(24)->minValue(0)->maxValue(45)
                                ->helperText('Kuo didesnis — tuo labiau persidengia tos pačios komandos žaidėjai.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            TextInput::make('h2h_gap')->label('Tarpas tarp komandų (vw)')
                                ->numeric()->default(0)->minValue(0)->maxValue(30)
                                ->helperText('Pastumia komandas tolyn viena nuo kitos link kraštų.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            Toggle::make('h2h_show_sponsors')->label('Rodyti rėmėjų juostą apačioje')->default(false)->live()
                                ->helperText('Komandų lentelės pakyla ir truputį sumažėja. Rėmėjus pasirink žemiau.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            FileUpload::make('h2h_sponsor_logo')->label('Centrinis rėmėjas — logo (vidury)')
                                ->acceptedFileTypes(['image/gif', 'image/png', 'image/webp', 'image/jpeg'])
                                ->disk('public')->directory('overlay-sponsors')
                                ->helperText('Įkėlus — matosi vidury tarp komandų; neįkėlus — nesimato.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            TextInput::make('h2h_sponsor_text')->label('Centrinis rėmėjas — tekstas')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),

                            Select::make('h2h_bg_mode')->label('Fonas (animuotas)')
                                ->options([
                                    'none'     => 'Nėra (permatomas)',
                                    'gradient' => 'Spalvų maišymas (temos spalvos)',
                                    'image'    => 'Fonas + nuotrauka (daugybinama, skraido)',
                                ])->default('none')->live()
                                ->helperText('„Spalvų maišymas" — lėtai plaukiantys spalvų debesys. „Fonas + nuotrauka" — įkelta nuotrauka padauginama ir skraido.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h'),
                            Select::make('h2h_bg_intensity')->label('Fono intensyvumas')
                                ->options(['subtle' => 'Subtilus', 'medium' => 'Vidutinis', 'bold' => 'Ryškus'])->default('subtle')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h' && ($get('h2h_bg_mode') ?? 'none') !== 'none'),
                            FileUpload::make('h2h_bg_image')->label('Fono nuotrauka (PNG su permatomu fonu — geriausia)')
                                ->acceptedFileTypes(['image/png', 'image/webp', 'image/gif', 'image/jpeg'])
                                ->disk('public')->directory('overlay-h2h-bg')
                                ->helperText('Pvz. apkirptas kamuoliukas ar logotipas. Sistema pati padaugins ir paskraidys.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h' && ($get('h2h_bg_mode') ?? 'none') === 'image'),
                            TextInput::make('h2h_bg_count')->label('Nuotraukų kiekis (nebūtina)')->numeric()->minValue(2)->maxValue(40)
                                ->placeholder('Auto pagal intensyvumą')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h' && ($get('h2h_bg_mode') ?? 'none') === 'image'),
                            TextInput::make('h2h_bg_speed')->label('Greitis (0.5 lėtai … 2 greitai, nebūtina)')->numeric()->minValue(0.3)->maxValue(3)->step(0.1)
                                ->placeholder('Auto')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'h2h' && ($get('h2h_bg_mode') ?? 'none') !== 'none'),

                            TextInput::make('score_games_per_set')->label('Geimų sete')->numeric()->default(6)->minValue(1)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            TextInput::make('score_tiebreak_at')->label('Tiebreak prie (geimų)')->numeric()->default(6)
                                ->helperText('Kai abi komandos pasiekia tiek geimų — tiebreak. „iki 6"→6, „iki 9"→8.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            TextInput::make('score_sets_to_win')->label('Laimėtų setų (mačui)')->numeric()->default(2)->minValue(1)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            Toggle::make('score_tiebreak')->label('Tiebreak sete')->default(true)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            TextInput::make('score_tiebreak_to')->label('Tiebreak iki')->numeric()->default(7)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            Toggle::make('score_super_tb')->label('Lemiamas setas – super tiebreak')->default(true)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            TextInput::make('score_super_tb_to')->label('Super tiebreak iki')->numeric()->default(10)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            Select::make('score_deuce_mode')->label('Lygiosios (40–40)')
                                ->options(['advantage' => 'Pranašumas', 'golden' => 'Auksinis taškas', 'star' => 'STAR'])->default('star')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            Select::make('score_position')->label('Pozicija')
                                ->options(['top-left' => 'Viršus — kairė', 'top-center' => 'Viršus — centras', 'top-right' => 'Viršus — dešinė',
                                    'bottom-left' => 'Apačia — kairė', 'bottom-center' => 'Apačia — centras', 'bottom-right' => 'Apačia — dešinė'])
                                ->default('top-left')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            TextInput::make('score_width')->label('Plotis (px)')->numeric()->default(520)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),
                            Toggle::make('show_level')->label('Rodyti lygį / kategoriją')->default(true)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'score'),

                            Select::make('variant')
                                ->label('Variantas')
                                ->options([
                                    'corner'     => 'Kampe (besikeičiantys logo)',
                                    'bar'        => 'Apačios juosta',
                                    'fullscreen' => 'Per visą ekraną',
                                ])
                                ->default('corner')->live()
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),
                            Select::make('corner_position')
                                ->label('Kampas')
                                ->options([
                                    'top-left'     => 'Viršus — kairė',
                                    'top-right'    => 'Viršus — dešinė',
                                    'bottom-left'  => 'Apačia — kairė',
                                    'bottom-right' => 'Apačia — dešinė',
                                ])
                                ->default('bottom-right')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors' && ($get('variant') ?? 'corner') === 'corner'),
                            Select::make('corner_size')
                                ->label('Dydis')
                                ->options(['s' => 'Mažas', 'm' => 'Vidutinis', 'l' => 'Didelis', 'xl' => 'Labai didelis'])
                                ->default('m')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors' && ($get('variant') ?? 'corner') === 'corner'),
                            Select::make('sponsor_ids')
                                ->label('Rėmėjai iš sąrašo')
                                ->multiple()
                                ->options(fn () => \App\Models\Sponsor::where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'))
                                ->visible(fn (Forms\Get $get) => self::sponsorFieldsVisible($get)),
                            Select::make('gallery_ids')
                                ->label('Galerija')
                                ->multiple()
                                ->options(fn () => \App\Models\Gallery::orderBy('name')->pluck('name', 'id'))
                                ->helperText('Pasirink vieną ar kelias galerijas — jų nuotraukos rodomos automatiškai.')
                                ->visible(fn (Forms\Get $get) => self::sponsorFieldsVisible($get)),
                            FileUpload::make('images')
                                ->label('Arba įkelk nuotraukas (masiškai)')
                                ->image()->multiple()->reorderable()
                                ->disk('public')->directory('overlay-sponsors')
                                ->visible(fn (Forms\Get $get) => self::sponsorFieldsVisible($get)),
                            TextInput::make('rotate_seconds')
                                ->label('Keitimo intervalas (s)')
                                ->numeric()->default(6)->minValue(2)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors' || ($get('type') === 'h2h' && $get('h2h_show_sponsors'))),

                            // ── Foto sienelė (step-and-repeat) ──
                            Select::make('pw_tile_size')->label('Logotipų dydis sienoje')
                                ->options(['s' => 'Maži', 'm' => 'Vidutiniai', 'l' => 'Dideli', 'xl' => 'Labai dideli'])->default('m')
                                ->helperText('Rėmėjų logotipai kartojami per visą foną. Iš „Galerija" / „Rėmėjai iš sąrašo" / įkeltų nuotraukų.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'photowall'),
                            Select::make('pw_gap')->label('Tarpai tarp logotipų')
                                ->options(['tight' => 'Maži', 'normal' => 'Vidutiniai', 'wide' => 'Dideli'])->default('normal')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'photowall'),
                            FileUpload::make('pw_main_logo')->label('Turnyro logo (centrinis)')
                                ->image()->disk('public')->directory('overlay-photowall')
                                ->helperText('Įkėlus — rodomas virš sienos pasirinktoje vietoje. Neįkėlus — imamas overlay turnyro logo.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'photowall'),
                            Select::make('pw_main_position')->label('Turnyro logo vieta')
                                ->options(['center' => 'Centre', 'top-center' => 'Centre viršuje', 'top-left' => 'Kairėje viršuje', 'top-right' => 'Dešinėje viršuje'])
                                ->default('center')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'photowall'),
                            Select::make('pw_main_size')->label('Turnyro logo dydis')
                                ->options(['s' => 'Mažas', 'm' => 'Vidutinis', 'l' => 'Didelis', 'xl' => 'Labai didelis'])->default('l')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'photowall'),
                        ])
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['name'] ?? 'Langas')
                        ->defaultItems(0)
                        ->addActionLabel('Pridėti langą'),
                ]),
        ]);
    }

    /** Sponsor source fields show for a sponsors/photowall window, or an h2h window with the bar on. */
    private static function sponsorFieldsVisible(Forms\Get $get): bool
    {
        $type = $get('type') ?? 'groups';

        return in_array($type, ['sponsors', 'photowall'], true) || ($type === 'h2h' && $get('h2h_show_sponsors'));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Pavadinimas')
                    ->searchable(),

                TextColumn::make('type')
                    ->label('Tipas')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'bracket' ? 'Brackets' : 'Grupės'),

                TextColumn::make('obs_url')
                    ->label('OBS URL')
                    ->state(fn ($record) => url('/overlay/' . $record->token))
                    ->copyable()->copyMessage('Nukopijuota!')->wrap()
                    ->description('Įklijuok į OBS → Sources → Browser'),

                TextColumn::make('control_url')
                    ->label('Valdymas (OBS dock)')
                    ->state(fn ($record) => url('/overlay/' . $record->token . '/control'))
                    ->copyable()->copyMessage('Nukopijuota!')->wrap()
                    ->description('Įklijuok į OBS → Docks → Custom Browser Docks'),

                TextColumn::make('score_url')
                    ->label('Rezultato valdymas (mob.)')
                    ->state(fn ($record) => url('/overlay/' . $record->token . '/score'))
                    ->copyable()->copyMessage('Nukopijuota!')->wrap()
                    ->description('Atidaryk telefone/planšetėje (reikia „Rezultatas" lango)'),
            ])
            ->actions([
                EditAction::make(),

                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOverlays::route('/'),
            'create' => Pages\CreateOverlay::route('/create'),
            'edit'   => Pages\EditOverlay::route('/{record}/edit'),
        ];
    }
}
