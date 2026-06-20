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
                                ->options(['groups' => 'Grupės', 'bracket' => 'Brackets', 'draw' => 'Traukimas', 'sponsors' => 'Rėmėjai', 'schedule' => 'Tvarkaraštis'])
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
                            FileUpload::make('images')->label('Arba įkelk rėmėjų logotipus')
                                ->image()->multiple()->reorderable()->disk('public')->directory('overlay-sponsors')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),
                            TextInput::make('rotate_seconds')->label('Rėmėjų slinkimo greitis (s/logo)')
                                ->numeric()->default(5)->minValue(2)
                                ->helperText('Juosta slenka po vieną logo; kuo didesnis skaičius, tuo lėčiau.')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'draw'),

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
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),
                            FileUpload::make('images')
                                ->label('Arba įkelk nuotraukas (masiškai)')
                                ->image()->multiple()->reorderable()
                                ->disk('public')->directory('overlay-sponsors')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),
                            TextInput::make('rotate_seconds')
                                ->label('Keitimo intervalas (s)')
                                ->numeric()->default(6)->minValue(2)
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),
                        ])
                        ->collapsible()
                        ->itemLabel(fn (array $state) => $state['name'] ?? 'Langas')
                        ->defaultItems(0)
                        ->addActionLabel('Pridėti langą'),
                ]),
        ]);
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

                TextColumn::make('token')
                    ->label('Token')
                    ->copyable(),
            ])
            ->actions([
                Tables\Actions\Action::make('copyUrl')
                    ->label('OBS URL')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->action(function () {})
                    ->extraAttributes(fn ($record) => [
                        'x-on:click' => self::copyOnClick(url('/overlay/' . $record->token)),
                    ]),

                Tables\Actions\Action::make('copyControlUrl')
                    ->label('Valdymas (OBS dock)')
                    ->icon('heroicon-o-play')
                    ->color('gray')
                    ->action(function () {})
                    ->extraAttributes(fn ($record) => [
                        'x-on:click' => self::copyOnClick(url('/overlay/' . $record->token . '/control')),
                    ]),

                EditAction::make(),

                DeleteAction::make(),
            ]);
    }

    /**
     * Robust copy-to-clipboard for a table action: synchronous textarea +
     * execCommand (works in non-secure contexts and before any Livewire
     * re-render), plus the async Clipboard API, plus a tooltip.
     */
    private static function copyOnClick(string $url): string
    {
        $u = json_encode($url);
        $msg = json_encode('Nukopijuota!');

        return "(() => { const u = {$u};"
            . " const t = document.createElement('textarea'); t.value = u; t.style.position = 'fixed'; t.style.top = '-1000px';"
            . " document.body.appendChild(t); t.focus(); t.select();"
            . " try { document.execCommand('copy'); } catch (e) {} t.remove();"
            . " if (navigator.clipboard) { try { navigator.clipboard.writeText(u); } catch (e) {} }"
            . " \$tooltip({$msg}, { timeout: 1500 }); })()";
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
