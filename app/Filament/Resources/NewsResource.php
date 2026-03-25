<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsResource\Pages;
use App\Models\News;
use App\Models\Tournament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

class NewsResource extends Resource
{
    protected static ?string $model = News::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Naujienos';

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
                            ->helperText('Slug bus naudojamas URL, pvz.: vilnius-open-2025-naujienos'),

                        Select::make('status')
                            ->label('Statusas')
                            ->required()
                            ->options([
                                'draft'     => 'Juodraštis',
                                'published' => 'Publikuota',
                            ])
                            ->default('draft'),

                        DateTimePicker::make('published_at')
                            ->label('Publikavimo data')
                            ->nullable(),

                        Select::make('tournament_id')
                            ->label('Turnyras')
                            ->nullable()
                            ->searchable()
                            ->options(function () {
                                return Tournament::with('translations')
                                    ->get()
                                    ->mapWithKeys(function ($tournament) {
                                        $title = $tournament->translation('lt')?->title ?? $tournament->slug;
                                        return [$tournament->id => $title];
                                    });
                            }),

                        Toggle::make('is_featured')
                            ->label('Rekomenduojama (rodoma viršuje)')
                            ->default(false),
                    ])
                    ->columns(2),

                // ── Cover image ───────────────────────────────────────────
                Section::make('Viršelio nuotrauka')
                    ->schema([
                        FileUpload::make('cover_image')
                            ->disk('public')
                            ->directory('news/covers')
                            ->image()
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // ── Translations ──────────────────────────────────────────
                Section::make('Tekstai (LT / EN)')
                    ->description('LT kairėje, EN dešinėje. Pridėkite vertimus kiekvienai kalbai.')
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

                                Textarea::make('excerpt')
                                    ->label('Trumpas aprašymas')
                                    ->rows(3)
                                    ->helperText('Trumpas aprašymas sąraše')
                                    ->columnSpanFull(),

                                RichEditor::make('content')
                                    ->label('Turinys')
                                    ->toolbarButtons([
                                        'bold',
                                        'italic',
                                        'h2',
                                        'h3',
                                        'bulletList',
                                        'orderedList',
                                        'link',
                                        'blockquote',
                                        'undo',
                                        'redo',
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->minItems(1)
                            ->maxItems(2)
                            ->label('Vertimai')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // ── Photos ────────────────────────────────────────────────
                Section::make('Papildomos nuotraukos')
                    ->schema([
                        FileUpload::make('photo_paths')
                            ->label('Nuotraukos')
                            ->multiple()
                            ->disk('public')
                            ->directory('news/photos')
                            ->image()
                            ->reorderable()
                            ->maxFiles(20)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                // ── Buttons ───────────────────────────────────────────────
                Section::make('Mygtukai')
                    ->schema([
                        Repeater::make('buttons')
                            ->schema([
                                TextInput::make('label_lt')
                                    ->label('🇱🇹 Tekstas')
                                    ->required(),

                                TextInput::make('label_en')
                                    ->label('🇬🇧 Text'),

                                TextInput::make('url')
                                    ->label('Nuoroda')
                                    ->url()
                                    ->required(),

                                Select::make('style')
                                    ->label('Stilius')
                                    ->options([
                                        'primary'   => 'Pirminis (aukso)',
                                        'outline'   => 'Kontūrinis',
                                        'secondary' => 'Antrinis',
                                    ])
                                    ->default('primary'),
                            ])
                            ->columns(2)
                            ->label('Mygtukai')
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

                TextColumn::make('title_lt')
                    ->label('Pavadinimas')
                    ->getStateUsing(fn ($record) => $record->translation('lt')?->title ?? '—')
                    ->searchable(query: function ($query, string $value) {
                        $query->whereHas('translations', fn ($q) => $q->where('locale', 'lt')->where('title', 'like', "%{$value}%"));
                    }),

                TextColumn::make('status')
                    ->label('Statusas')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft'     => 'warning',
                        default     => 'secondary',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'Publikuota',
                        'draft'     => 'Juodraštis',
                        default     => $state,
                    }),

                TextColumn::make('tournament.slug')
                    ->label('Turnyras')
                    ->getStateUsing(fn ($record) => $record->tournament?->translation('lt')?->title ?? '—'),

                TextColumn::make('published_at')
                    ->label('Publikuota')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                IconColumn::make('is_featured')
                    ->label('Rekomenduojama')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray'),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft'     => 'Juodraštis',
                        'published' => 'Publikuota',
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
            'index'  => Pages\ListNews::route('/'),
            'create' => Pages\CreateNews::route('/create'),
            'edit'   => Pages\EditNews::route('/{record}/edit'),
        ];
    }
}
