<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SponsorResource\Pages;
use App\Models\Sponsor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SponsorResource extends Resource
{
    protected static ?string $model = Sponsor::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Rėmėjai';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required(),

                FileUpload::make('logo')
                    ->disk('public')
                    ->directory('sponsors')
                    ->image()
                    ->required(),

                TextInput::make('url')
                    ->url()
                    ->nullable(),

                Select::make('category')
                    ->required()
                    ->options([
                        'gold' => 'Auksinis',
                        'silver' => 'Sidabrinis',
                        'bronze' => 'Bronzinis',
                        'general' => 'Bendras',
                    ]),

                Toggle::make('is_general')
                    ->label('Bendras rėmėjas')
                    ->helperText('Jei įjungta — rėmėjas rodomas kaip bendras (nepriklausomai nuo turnyro)')
                    ->default(false),

                Select::make('tournaments')
                    ->label('Turnyrai')
                    ->multiple()
                    ->relationship('tournaments', 'slug')
                    ->preload()
                    ->helperText('Pasirink turnyrą(-us) kuriam šis rėmėjas priklauso'),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),

                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Pavadinimas')
                    ->searchable(),

                ImageColumn::make('logo')
                    ->disk('public')
                    ->label('Logotipas'),

                TextColumn::make('category')
                    ->label('Kategorija')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'gold' => 'warning',
                        'silver' => 'secondary',
                        'bronze' => 'danger',
                        'general' => 'info',
                        default => 'secondary',
                    }),

                TextColumn::make('tournaments_count')
                    ->label('Turnyrai')
                    ->counts('tournaments')
                    ->badge(),

                IconColumn::make('is_general')
                    ->label('Bendras')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Aktyvus')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Eilė'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'gold' => 'Auksinis',
                        'silver' => 'Sidabrinis',
                        'bronze' => 'Bronzinis',
                        'general' => 'Bendras',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Aktyvus'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSponsors::route('/'),
            'create' => Pages\CreateSponsor::route('/create'),
            'edit' => Pages\EditSponsor::route('/{record}/edit'),
        ];
    }
}
