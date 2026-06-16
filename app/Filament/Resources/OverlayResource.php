<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OverlayResource\Pages;
use App\Models\Overlay;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OverlayResource extends Resource
{
    protected static ?string $model = Overlay::class;

    protected static ?string $navigationIcon = 'heroicon-o-tv';

    protected static ?string $navigationLabel = 'Overlay\'ai';

    protected static ?string $navigationGroup = 'Transliacijos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Pavadinimas')
                    ->required(),

                Select::make('type')
                    ->label('Tipas')
                    ->options([
                        'group_standings' => 'Grupės',
                        'bracket'         => 'Brackets',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('tournament_external_id')
                    ->label('Tournated turnyro ID')
                    ->helperText('Pvz. 10229')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'group_standings'),

                Section::make('Išvaizda')
                    ->schema([
                        TextInput::make('config.title')
                            ->label('Antraštė'),

                        ColorPicker::make('config.accent_color')
                            ->label('Akcento spalva')
                            ->default('#C9A84C'),

                        FileUpload::make('config.logo')
                            ->label('Logotipas')
                            ->image()
                            ->directory('overlay-logos'),

                        Select::make('config.position')
                            ->label('Pozicija ekrane')
                            ->options([
                                'bottom-left'  => 'Apačia kairė',
                                'bottom-right' => 'Apačia dešinė',
                                'top-left'     => 'Viršus kairė',
                                'center'       => 'Centras',
                            ])
                            ->default('bottom-left'),

                        CheckboxList::make('config.visible_columns')
                            ->label('Rodomi stulpeliai')
                            ->options([
                                'place'   => 'Vieta',
                                'name'    => 'Pora',
                                'wins'    => 'Laimėjimai',
                                'losses'  => 'Pralaimėjimai',
                                'played'  => 'Sužaista',
                            ])
                            ->visible(fn (Forms\Get $get) => $get('type') === 'group_standings'),
                    ]),

                Section::make('Bracket duomenys')
                    ->visible(fn (Forms\Get $get) => $get('type') === 'bracket')
                    ->schema([
                        Textarea::make('bracket_data')
                            ->label('Bracket JSON')
                            ->helperText('Rankiniu būdu suvestas tinklelis JSON formatu: {"rounds":[{"matches":[{"teams":[{"name":"...","score":"","winner":false}]}]}]}')
                            ->formatStateUsing(fn ($state) => filled($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? json_decode($state, true) : null)
                            ->rows(10),
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
                        'x-on:click' => 'window.navigator.clipboard.writeText('
                            . json_encode(url('/overlay/' . $record->token))
                            . '); $tooltip(' . json_encode('Nukopijuota!') . ', { timeout: 1500 })',
                    ]),

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
