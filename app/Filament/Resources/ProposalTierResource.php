<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProposalTierResource\Pages;
use App\Models\ProposalTier;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProposalTierResource extends Resource
{
    protected static ?string $model = ProposalTier::class;

    protected static ?string $navigationIcon  = 'heroicon-o-currency-euro';
    protected static ?string $navigationLabel = 'Rėmimo paketai';
    protected static ?string $modelLabel      = 'Paketas';
    protected static ?string $pluralModelLabel = 'Paketai';
    protected static ?int    $navigationSort  = 51;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Paketo informacija')
                ->schema([
                    TextInput::make('name')
                        ->label('Pavadinimas')
                        ->required()
                        ->placeholder('Start / Growth / Title'),

                    TextInput::make('tagline')
                        ->label('Aprašymas (kam tinka)')
                        ->placeholder('Mažam verslui, vietinei rinkai'),

                    TextInput::make('price')
                        ->label('Kaina (€)')
                        ->numeric()
                        ->required()
                        ->placeholder('200'),

                    TextInput::make('price_suffix')
                        ->label('Kainos papildymas')
                        ->placeholder('+ PVM')
                        ->helperText('Pvz. "+ PVM", "/ mėn.", "nuo". Rodoma šalia kainos.'),

                    TextInput::make('slots_total')
                        ->label('Iš viso vietų')
                        ->numeric()
                        ->nullable()
                        ->helperText('Palikite tuščią jei vietų skaičius neribotas.'),

                    TextInput::make('slots_taken')
                        ->label('Užimtų vietų')
                        ->numeric()
                        ->default(0),

                    TextInput::make('sort_order')
                        ->label('Rikiavimo tvarka')
                        ->numeric()
                        ->default(0),
                ])
                ->columns(2),

            Section::make('Privalumai')
                ->schema([
                    Repeater::make('benefits')
                        ->label('Privalumų sąrašas')
                        ->schema([
                            TextInput::make('benefit')
                                ->label('Privalumas')
                                ->required()
                                ->placeholder('Logotipas ant marškinėlių'),
                        ])
                        ->itemLabel(fn (array $state) => $state['benefit'] ?? null)
                        ->collapsible()
                        ->reorderable()
                        ->columnSpanFull(),
                ]),

            Section::make('Vizualiniai nustatymai')
                ->schema([
                    Toggle::make('highlighted')
                        ->label('Rekomenduojamas (aukso spalvos rėmelis)')
                        ->helperText('Pažymėkite vieną paketo kaip rekomenduojamą — jis išskiriamas vizualiai.'),

                    Toggle::make('is_active')
                        ->label('Aktyvus (rodomas puslapyje)')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(40),

                TextColumn::make('name')
                    ->label('Pavadinimas')
                    ->weight('bold')
                    ->searchable(),

                TextColumn::make('price')
                    ->label('Kaina')
                    ->formatStateUsing(fn ($state, $record) =>
                        number_format($state, 0, '.', ' ') . ' €' .
                        ($record->price_suffix ? ' ' . $record->price_suffix : '')
                    )
                    ->sortable(),

                TextColumn::make('tagline')
                    ->label('Aprašymas')
                    ->limit(40)
                    ->color('gray'),

                TextColumn::make('slots_info')
                    ->label('Vietos')
                    ->getStateUsing(fn ($record) =>
                        $record->slots_total
                            ? "{$record->slots_taken} / {$record->slots_total}"
                            : '∞'
                    ),

                IconColumn::make('highlighted')
                    ->label('Rekomenduojamas')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Aktyvus')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProposalTiers::route('/'),
            'create' => Pages\CreateProposalTier::route('/create'),
            'edit'   => Pages\EditProposalTier::route('/{record}/edit'),
        ];
    }
}
