<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StatResource\Pages;
use App\Models\Stat;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StatResource extends Resource
{
    protected static ?string $model = Stat::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Statistika';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')
                    ->required()
                    ->unique(ignoreRecord: true),

                TextInput::make('value')
                    ->numeric()
                    ->required(),

                TextInput::make('label_lt')
                    ->required()
                    ->label('Etiketė (LT)'),

                TextInput::make('label_en')
                    ->required()
                    ->label('Etiketė (EN)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->label('Raktas')
                    ->searchable(),

                TextColumn::make('value')
                    ->label('Reikšmė'),

                TextColumn::make('label_lt')
                    ->label('Etiketė (LT)'),

                TextColumn::make('label_en')
                    ->label('Etiketė (EN)'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStats::route('/'),
            'create' => Pages\CreateStat::route('/create'),
            'edit' => Pages\EditStat::route('/{record}/edit'),
        ];
    }
}
