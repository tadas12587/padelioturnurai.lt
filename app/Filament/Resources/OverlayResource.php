<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OverlayResource\Pages;
use App\Models\Overlay;
use App\Services\OverlayData;
use Filament\Forms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
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
                                ->options(['groups' => 'Grupės', 'bracket' => 'Brackets', 'sponsors' => 'Rėmėjai'])
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

                                    Select::make('group_id')
                                        ->label('Pogrupis')
                                        ->placeholder('Visi pogrupiai')
                                        ->options(function ($livewire, Forms\Get $get) {
                                            $tid = data_get($livewire, 'data.tournament_external_id');
                                            $cid = $get('category_id');
                                            if (! $tid || ! $cid) {
                                                return ['' => 'Visi pogrupiai'];
                                            }
                                            $out = ['' => 'Visi pogrupiai'];
                                            foreach (app(OverlayData::class)->groups((string) $tid, (int) $cid) as $g) {
                                                $out[$g['id']] = $g['name'] ?? ('#' . $g['id']);
                                            }
                                            return $out;
                                        }),
                                ])
                                ->columns(2)
                                ->defaultItems(1),

                            Select::make('category_id')
                                ->label('Kategorija (bracketas)')
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

                            Select::make('variant')
                                ->label('Variantas')
                                ->options([
                                    'corner'     => 'Kampe (besikeičiantys logo)',
                                    'bar'        => 'Apačios juosta',
                                    'fullscreen' => 'Per visą ekraną',
                                ])
                                ->default('corner')
                                ->visible(fn (Forms\Get $get) => ($get('type') ?? 'groups') === 'sponsors'),
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
