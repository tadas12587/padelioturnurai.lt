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

    protected static ?string $navigationGroup = 'Svetainė';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('key')
                    ->required()
                    ->unique(ignoreRecord: true),

                \Filament\Forms\Components\Radio::make('mode')
                    ->label('Kaip skaičiuoti')
                    ->options(['auto' => 'Automatiškai iš turnyrų', 'manual' => 'Įvesti ranka'])
                    ->default('manual')
                    ->live()
                    ->disabled(fn ($get) => ! isset(Stat::AUTO_KEYS[$get('key')]))
                    ->helperText(fn ($get) => isset(Stat::AUTO_KEYS[$get('key')])
                        ? 'Automatiškai: '.Stat::AUTO_KEYS[$get('key')].' (dabar '.Stat::computed($get('key')).').'
                        : 'Automatinis skaičiavimas galimas raktams: '.implode(', ', array_keys(Stat::AUTO_KEYS)).'.'),

                TextInput::make('base_value')
                    ->label('Pridėti prie automatinės reikšmės')
                    ->numeric()
                    ->default(0)
                    ->helperText('Istorija, kurios nėra turnyrų sąraše (pvz. ankstesnių metų dalyviai).')
                    ->visible(fn ($get) => $get('mode') === 'auto'),

                TextInput::make('value')
                    ->label('Reikšmė')
                    ->numeric()
                    ->required(fn ($get) => $get('mode') !== 'auto')
                    ->default(0)
                    ->visible(fn ($get) => $get('mode') !== 'auto'),

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

                TextColumn::make('display_value')
                    ->label('Rodoma reikšmė')
                    ->description(fn (Stat $record) => $record->mode === 'auto' ? 'automatiškai'.($record->base_value ? ' (+'.$record->base_value.')' : '') : 'ranka'),

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
