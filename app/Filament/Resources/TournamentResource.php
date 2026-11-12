<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TournamentResource\Pages;
use App\Http\Controllers\PushController;
use App\Models\PushSubscription;
use App\Models\ScheduleSnapshot;
use App\Models\Tournament;
use App\Models\TournatedSync;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TournamentResource extends Resource
{
    protected static ?string $model = Tournament::class;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Turnyrai';

    protected static ?string $navigationGroup = 'Turnyrai';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ── Tournated: schedule, results, phone notifications ─────
                Section::make('Tournated: grafikas, rezultatai ir pranešimai')
                    ->description('Įrašius Tournated turnyro ID, svetainė pati kas minutę (turnyro dienomis) atsisiunčia grafiką ir rezultatus, skaičiuoja vėlavimus ir siunčia pranešimus žaidėjams. Turnyro puslapyje atsiranda mygtukas „Grafikas ir rezultatai".')
                    ->schema([
                        TextInput::make('tournated_id')
                            ->label('Tournated turnyro ID arba nuoroda')
                            ->placeholder('pvz. 11532 arba https://play.padel.lt/tournament/11532')
                            ->nullable()
                            ->live(onBlur: true) // shows the switches below as soon as an ID is typed
                            ->formatStateUsing(fn ($state) => $state)
                            ->dehydrateStateUsing(fn ($state) => Tournament::parseTournatedId($state))
                            ->rule(fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                if (! $value) {
                                    return;
                                }
                                $id = Tournament::parseTournatedId($value);
                                if (! $id) {
                                    $fail('Įrašyk skaičių (pvz. 11532) arba play.padel.lt turnyro nuorodą.');
                                } elseif (Tournament::where('tournated_id', $id)->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))->exists()) {
                                    $fail('Šis Tournated turnyras jau priskirtas kitam turnyrui.');
                                }
                            })
                            ->suffixAction(
                                \Filament\Forms\Components\Actions\Action::make('fillFromTournated')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->tooltip('Užpildyti datas iš Tournated')
                                    ->action(function ($state, $set) {
                                        $id = Tournament::parseTournatedId($state);
                                        try {
                                            $t = \App\Services\Tournated\TournatedApi::fromConfig()->tournament((string) $id);
                                        } catch (\Throwable $e) {
                                            Notification::make()->title('Nepavyko gauti iš Tournated')->body($e->getMessage())->danger()->send();

                                            return;
                                        }
                                        $tz = $t['tournament_tz'] ?? 'Europe/Vilnius';
                                        $set('tournated_id', $id);
                                        if (! empty($t['tournament_start_date'])) {
                                            $set('date_start', \Carbon\Carbon::parse($t['tournament_start_date'])->setTimezone($tz)->toDateString());
                                        }
                                        if (! empty($t['tournament_end_date'])) {
                                            $set('date_end', \Carbon\Carbon::parse($t['tournament_end_date'])->setTimezone($tz)->toDateString());
                                        }
                                        Notification::make()->title($t['tournament_name'] ?? 'Rasta')->body('Datos užpildytos. Pavadinimą įrašyk skiltyje „Tekstai".')->success()->send();
                                    })
                            ),

                        \Filament\Forms\Components\Grid::make(3)
                            ->schema([
                                Toggle::make('sync_enabled')->label('Atnaujinti iš Tournated')->default(true)
                                    ->helperText('Išjungus duomenys nebeatnaujinami.'),
                                Toggle::make('show_schedule')->label('Rodyti grafiką viešai')->default(true)
                                    ->helperText('Išjungus grafiko puslapis ir mygtukas paslepiami.'),
                                Toggle::make('push_enabled')->label('Pranešimai žaidėjams')->default(true)
                                    ->helperText('„Tavo eilė", kortų ir laikų pakeitimai.'),
                            ])
                            ->visible(fn ($get) => filled($get('tournated_id'))),

                        Placeholder::make('tournated_status')
                            ->label('Būsena')
                            ->content(fn (?Tournament $record) => new HtmlString(self::syncStatusHtml($record)))
                            ->visible(fn (?Tournament $record) => (bool) $record?->tournated_id),
                    ]),

                // ── Core fields ───────────────────────────────────────────
                Section::make('Pagrindinė informacija')
                    ->schema([
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('Pvz.: vilnius-open-2025'),

                        Select::make('status')
                            ->label('Statusas')
                            ->required()
                            ->options([
                                'upcoming' => 'Būsimas',
                                'active'   => 'Aktyvus',
                                'past'     => 'Praeitas',
                            ]),

                        DatePicker::make('date_start')
                            ->label('Pradžios data')
                            ->required(),

                        DatePicker::make('date_end')
                            ->label('Pabaigos data')
                            ->nullable(),

                        TextInput::make('location')
                            ->label('Vieta')
                            ->required(),

                        TextInput::make('participants_count')
                            ->label('Dalyvių skaičius')
                            ->numeric()
                            ->default(0)
                            ->disabled(fn ($get) => filled($get('tournated_id')) && $get('participants_auto'))
                            ->dehydrated()
                            ->helperText(fn ($get) => filled($get('tournated_id')) && $get('participants_auto') ? 'Skaičiuojama automatiškai iš Tournated (skirtingi žaidėjai mačuose).' : null),

                        Toggle::make('participants_auto')
                            ->label('Dalyvių skaičių imti iš Tournated')
                            ->default(true)
                            ->live()
                            ->visible(fn ($get) => filled($get('tournated_id'))),
                    ])
                    ->columns(2),

                // ── Registration ──────────────────────────────────────────
                Section::make('Registracija')
                    ->schema([
                        Toggle::make('registration_active')
                            ->label('Registracija aktyvi')
                            ->helperText('Įjungus — rodomas mygtukas „Registruotis" su nuoroda žemiau.')
                            ->columnSpanFull(),

                        Toggle::make('notify_enabled')
                            ->label('Išankstinė registracija (pranešimas)')
                            ->helperText('Įjungus — visur prie šio turnyro rodomas mygtukas „Pranešk kai prasidės registracija".')
                            ->columnSpanFull(),

                        TextInput::make('registration_url')
                            ->label('Registracijos nuoroda')
                            ->nullable()
                            ->url()
                            ->placeholder('https://...')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // ── Groups / Tables ───────────────────────────────────────
                Section::make('Grupės / Lentelės')
                    ->description('Nuoroda į išorinį puslapį su grupėmis ar lentelėmis (pvz. Playtomic). Turnyro puslapyje bus rodomas atskiras mygtukas.')
                    ->schema([
                        TextInput::make('results_url')
                            ->label('Grupės / Lentelės — nuoroda')
                            ->nullable()
                            ->url()
                            ->placeholder('https://play.padelioturnyrai.lt/...')
                            ->helperText('Jei tuščia — mygtukas „Grupės / Lentelės" nematomas.')
                            ->columnSpanFull(),
                    ]),

                // ── Results ───────────────────────────────────────────────
                Section::make('Rezultatai')
                    ->description('Rezultatai gali būti rašomi tekstu arba nuoroda į išorinį puslapį — pasirinkite vieną variantą.')
                    ->schema([
                        Radio::make('results_type')
                            ->label('Rezultatų tipas')
                            ->options([
                                'none' => 'Nėra rezultatų',
                                'text' => 'Tekstas (rašyti čia)',
                                'link' => 'Nuoroda į išorinį puslapį',
                            ])
                            ->default('none')
                            ->live()
                            ->columnSpanFull()
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($state, $record, $set) {
                                if (! $record) {
                                    return;
                                }
                                if ($record->results_link) {
                                    $set('results_type', 'link');
                                } elseif ($record->results_text) {
                                    $set('results_type', 'text');
                                } else {
                                    $set('results_type', 'none');
                                }
                            }),

                        Textarea::make('results_text')
                            ->label('Rezultatai (tekstas)')
                            ->rows(6)
                            ->placeholder("1. Jonas Jonaitis / Petras Petraitis\n2. Antanas Antanaitis / Vardenis Pavardenis\n...")
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('results_type') === 'text'),

                        TextInput::make('results_link')
                            ->label('Rezultatų nuoroda (išorinis puslapis)')
                            ->url()
                            ->nullable()
                            ->placeholder('https://...')
                            ->helperText('Turnyro puslapyje bus rodoma nuoroda į rezultatus.')
                            ->columnSpanFull()
                            ->visible(fn ($get) => $get('results_type') === 'link'),
                    ]),

                // ── Gallery ───────────────────────────────────────────────
                Section::make('Galerija')
                    ->description('Turnyro puslapyje rodomos pirmosios nuotraukos. Jei yra išorinė galerija — nurodykite nuorodą, bus rodomas mygtukas „Visa galerija".')
                    ->schema([
                        TextInput::make('gallery_url')
                            ->label('Pilnos galerijos nuoroda (išorinis puslapis)')
                            ->url()
                            ->nullable()
                            ->placeholder('https://photos.google.com/... arba Dropbox, OneDrive ir kt.')
                            ->helperText('Neprivaloma. Jei užpildyta — po nuotraukomis atsiras mygtukas „Visa galerija".')
                            ->columnSpanFull(),
                    ]),

                // ── Cover image ───────────────────────────────────────────
                Section::make('Viršelio nuotrauka')
                    ->schema([
                        FileUpload::make('cover_image')
                            ->disk('public')
                            ->directory('tournaments/covers')
                            ->image()
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // ── Translations ──────────────────────────────────────────
                Section::make('Tekstai (LT / EN)')
                    ->description('Pridėkite pavadinimą, aprašymą ir teksto rezultatus kiekvienai kalbai.')
                    ->schema([
                        Repeater::make('translations')
                            ->relationship('translations')
                            ->schema([
                                Select::make('locale')
                                    ->label('Kalba')
                                    ->options(['lt' => 'Lietuvių', 'en' => 'English'])
                                    ->required()
                                    ->columnSpanFull(),

                                TextInput::make('title')
                                    ->label('Pavadinimas')
                                    ->required()
                                    ->columnSpanFull(),

                                Textarea::make('description')
                                    ->label('Aprašymas')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                            ->minItems(1)
                            ->maxItems(2)
                            ->label('Vertimai')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // ── Bulk photo upload ─────────────────────────────────────
                Section::make('Masinis nuotraukų įkėlimas')
                    ->description('Pasirinkite kelias nuotraukas vienu metu. Jos bus automatiškai sumažintos iki 1920×1080px.')
                    ->schema([
                        FileUpload::make('bulk_photos')
                            ->multiple()
                            ->image()
                            ->disk('public')
                            ->directory('tournaments/photos')
                            ->label('Įkelti nuotraukas')
                            ->maxFiles(50)
                            ->maxSize(20480)
                            ->columnSpanFull()
                            ->dehydrated(false),
                    ])
                    ->columnSpanFull(),

                // ── Individual photo management ───────────────────────────
                Section::make('Nuotraukų valdymas')
                    ->description('Peržiūrėkite, pašalinkite arba pakeiskite eiliškumą.')
                    ->schema([
                        Repeater::make('photos')
                            ->relationship('photos')
                            ->schema([
                                FileUpload::make('path')
                                    ->disk('public')
                                    ->image()
                                    ->required()
                                    ->directory('tournaments/photos'),
                                TextInput::make('sort_order')
                                    ->numeric()
                                    ->default(0)
                                    ->label('Eiliškumas'),
                            ])
                            ->label('Nuotraukos')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_image')
                    ->disk('public')
                    ->label('Viršelis'),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),

                TextColumn::make('location')
                    ->label('Vieta')
                    ->searchable(),

                TextColumn::make('status')
                    ->label('Statusas')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'upcoming' => 'warning',
                        'active'   => 'success',
                        'past'     => 'secondary',
                        default    => 'secondary',
                    }),

                TextColumn::make('date_start')
                    ->label('Pradžia')
                    ->date('Y-m-d'),

                TextColumn::make('participants_count')
                    ->label('Dalyviai'),

                TextColumn::make('tournated_id')
                    ->label('Tournated')
                    ->badge()
                    ->formatStateUsing(fn ($state, Tournament $record) => self::syncBadge($record)[0])
                    ->color(fn (Tournament $record) => self::syncBadge($record)[1])
                    ->description(fn (Tournament $record) => $record->tournated_id ? '#'.$record->tournated_id : null)
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'upcoming' => 'Būsimas',
                        'active'   => 'Aktyvus',
                        'past'     => 'Praeitas',
                    ]),
            ])
            ->actions([
                Action::make('schedule')
                    ->label('Grafikas')
                    ->icon('heroicon-o-calendar-days')
                    ->url(fn (Tournament $record) => route('schedule.show', $record->tournated_id), shouldOpenInNewTab: true)
                    ->visible(fn (Tournament $record) => $record->tournated_id && ScheduleSnapshot::where('tournament_external_id', $record->tournated_id)->exists()),
                ActionGroup::make([
                    Action::make('syncNow')
                        ->label('Atnaujinti iš Tournated dabar')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (Tournament $record) => (bool) $record->tournated_id)
                        ->action(function (Tournament $record) {
                            $code = Artisan::call('tournated:sync', ['tournament' => [$record->tournated_id]]);
                            $sync = TournatedSync::where('tournament_external_id', $record->tournated_id)->first();
                            Notification::make()
                                ->title($code === 0 ? 'Atnaujinta: '.($sync?->match_count ?? 0).' mačų' : 'Nepavyko atnaujinti')
                                ->body($sync?->last_error)
                                ->{$code === 0 ? 'success' : 'danger'}()
                                ->send();
                        }),
                    Action::make('testPush')
                        ->label('Bandomasis pranešimas')
                        ->icon('heroicon-o-bell-alert')
                        ->visible(fn (Tournament $record) => (bool) $record->tournated_id)
                        ->modalDescription('Išsiunčia „Tavo eilė! Eik į Kortą 1" telefonams, kurie šiame turnyre įsijungė pranešimus. Pasirink žaidėją arba palik tuščią – gaus visi.')
                        ->form(fn (Tournament $record) => [
                            Select::make('player')
                                ->label('Žaidėjas')
                                ->placeholder('Visi įsijungę pranešimus')
                                ->options(PushSubscription::where('tournament_external_id', $record->tournated_id)->pluck('player', 'player_key'))
                                ->helperText(PushSubscription::where('tournament_external_id', $record->tournated_id)->count().' telefon(-ai) įsijungę pranešimus.'),
                        ])
                        ->action(function (Tournament $record, array $data) {
                            $subs = PushSubscription::where('tournament_external_id', $record->tournated_id)
                                ->when($data['player'] ?? null, fn ($q, $key) => $q->where('player_key', $key))->get();
                            $push = PushController::webPush();
                            $ok = 0;
                            foreach ($subs as $sub) {
                                $res = $push->send($sub->toWebPush(), json_encode([
                                    'title' => 'Tavo eilė! Eik į Kortą 1',
                                    'body' => 'Bandomasis pranešimas · padelioturnyrai.lt',
                                    'url' => '/grafikas/'.$record->tournated_id.'?z='.rawurlencode($sub->player),
                                    'tag' => 'test:'.now()->timestamp,
                                ], JSON_UNESCAPED_UNICODE), 300);
                                $res['gone'] ? $sub->delete() : null;
                                $ok += $res['ok'] ? 1 : 0;
                            }
                            Notification::make()->title("Išsiųsta: {$ok} iš {$subs->count()}")->{$ok ? 'success' : 'warning'}()->send();
                        }),
                    EditAction::make(),
                    DeleteAction::make(),
                ]),
            ]);
    }

    /** @return array{0: string, 1: string} label + colour for the Tournated sync state */
    private static function syncBadge(Tournament $record): array
    {
        if (! $record->tournated_id) {
            return ['—', 'gray'];
        }
        $s = $record->tournatedSync;
        if (! $s || ! $s->last_attempt_at) {
            return ['Laukia', 'gray'];
        }
        if (! $s->last_success_at || ($s->last_attempt_at > $s->last_success_at)) {
            return ['Klaida', 'danger'];
        }

        return [$s->last_error ? 'Veikia (su įspėjimu)' : 'Veikia', $s->last_error ? 'warning' : 'success'];
    }

    private static function syncStatusHtml(?Tournament $record): string
    {
        $s = $record?->tournatedSync;
        if (! $s || ! $s->last_attempt_at) {
            return 'Dar nesinchronizuota – pirmas atnaujinimas per kelias minutes (arba „Atnaujinti iš Tournated dabar" sąraše).';
        }
        $lines = [
            'Paskutinis sėkmingas: <b>'.e($s->last_success_at?->timezone('Europe/Vilnius')->format('Y-m-d H:i') ?? '—').'</b>',
            'Mačų: <b>'.(int) $s->match_count.'</b> · šaltinis: '.e($s->last_source === 'graphql' ? 'play.padel.lt (atsarginis)' : 'Tournated API'),
            'Pranešimus įsijungę telefonai: <b>'.PushSubscription::where('tournament_external_id', $record->tournated_id)->count().'</b>',
        ];
        if ($s->last_error) {
            $lines[] = '<span style="color:#d97706">Įspėjimas: '.e(mb_substr($s->last_error, 0, 300)).'</span>';
        }
        $lines[] = '<a href="'.e(route('schedule.show', $record->tournated_id)).'" target="_blank" style="text-decoration:underline">Atidaryti grafiką ↗</a>';

        return implode('<br>', $lines);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTournaments::route('/'),
            'create' => Pages\CreateTournament::route('/create'),
            'edit'   => Pages\EditTournament::route('/{record}/edit'),
        ];
    }
}
