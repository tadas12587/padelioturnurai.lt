<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TournamentResource\Pages;
use App\Models\Tournament;
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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                            ->default(0),
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

                IconColumn::make('results_url')
                    ->label('Grupės/Lentelės')
                    ->boolean()
                    ->trueIcon('heroicon-o-link')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->getStateUsing(fn ($record) => (bool) $record->results_url),
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
                EditAction::make(),
                DeleteAction::make(),
            ]);
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
