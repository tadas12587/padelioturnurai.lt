<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TournamentResource\Pages;
use App\Models\Tournament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\BadgeColumn;
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
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),

                Select::make('status')
                    ->required()
                    ->options([
                        'upcoming' => 'Būsimas',
                        'active' => 'Aktyvus',
                        'past' => 'Praeitas',
                    ]),

                DatePicker::make('date_start')
                    ->required(),

                DatePicker::make('date_end')
                    ->nullable(),

                TextInput::make('location')
                    ->required(),

                TextInput::make('participants_count')
                    ->numeric()
                    ->default(0),

                Toggle::make('registration_active')
                    ->label('Registracija aktyvi'),

                TextInput::make('registration_url')
                    ->nullable()
                    ->url(),

                FileUpload::make('cover_image')
                    ->disk('public')
                    ->directory('tournaments/covers')
                    ->image()
                    ->nullable()
                    ->columnSpanFull(),

                Repeater::make('translations')
                    ->relationship('translations')
                    ->schema([
                        Select::make('locale')
                            ->options(['lt' => 'Lietuvių', 'en' => 'English'])
                            ->required(),
                        TextInput::make('title')
                            ->required(),
                        Textarea::make('description')
                            ->rows(4),
                        Textarea::make('results_text')
                            ->rows(3),
                    ])
                    ->minItems(1)
                    ->maxItems(2)
                    ->label('Vertimai (LT / EN)')
                    ->columnSpanFull(),

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
                            ->default(0),
                    ])
                    ->label('Nuotraukos')
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
                        'active' => 'success',
                        'past' => 'secondary',
                        default => 'secondary',
                    }),

                TextColumn::make('date_start')
                    ->label('Pradžia')
                    ->date('Y-m-d'),

                TextColumn::make('participants_count')
                    ->label('Dalyviai'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'upcoming' => 'Būsimas',
                        'active' => 'Aktyvus',
                        'past' => 'Praeitas',
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
            'index' => Pages\ListTournaments::route('/'),
            'create' => Pages\CreateTournament::route('/create'),
            'edit' => Pages\EditTournament::route('/{record}/edit'),
        ];
    }
}
