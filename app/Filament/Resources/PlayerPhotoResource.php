<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlayerPhotoResource\Pages;
use App\Models\Overlay;
use App\Models\PlayerPhoto;
use App\Services\OverlayData;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlayerPhotoResource extends Resource
{
    protected static ?string $model = PlayerPhoto::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Transliacijos';

    protected static ?string $navigationLabel = 'Žaidėjų nuotraukos';

    protected static ?string $modelLabel = 'Žaidėjo nuotrauka';

    protected static ?string $pluralModelLabel = 'Žaidėjų nuotraukos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tournament_external_id')
                ->label('Turnyro ID')
                ->options(fn () => self::tournamentOptions())
                ->searchable()->required(),
            Forms\Components\TextInput::make('name')->label('Vardas')->required(),
            Forms\Components\Select::make('gender')->label('Lytis')
                ->options(['V' => 'Vyras', 'M' => 'Moteris'])->default('V')->required(),
            Forms\Components\TextInput::make('rating_type')->label('Reitingo tipas')->maxLength(40),
            Forms\Components\TextInput::make('rating_points')->label('Reitingo taškai')->maxLength(40),
            Forms\Components\TextInput::make('country')->label('Šalis')->maxLength(60),
            Forms\Components\TextInput::make('city')->label('Miestas')->maxLength(60),
            Forms\Components\FileUpload::make('photo')
                ->label('Nuotrauka (GIF/PNG, apkarpytas žmogus, skaidrus fonas)')
                ->acceptedFileTypes(['image/gif', 'image/png', 'image/webp'])
                ->disk('public')->directory('player-photos'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('photo')->label('Nuotrauka')->disk('public')->height(52),
                Tables\Columns\TextColumn::make('name')->label('Vardas')->searchable(),
                Tables\Columns\TextColumn::make('gender')->label('Lytis')->badge()
                    ->formatStateUsing(fn ($state) => $state === 'M' ? 'Moteris' : 'Vyras'),
                Tables\Columns\TextColumn::make('tournament_external_id')->label('Turnyras')->searchable(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    /** @return array<string,string> distinct tournament ids used by overlays */
    public static function tournamentOptions(): array
    {
        return Overlay::query()
            ->whereNotNull('tournament_external_id')->where('tournament_external_id', '!=', '')
            ->pluck('tournament_external_id', 'tournament_external_id')
            ->unique()->all();
    }

    /**
     * Upsert a player_photos row for every person of a tournament. Keeps any
     * existing gender/photo; guesses gender from the category on first insert.
     */
    public static function loadPeople(string $tid, string $defaultGender = 'V'): int
    {
        $data = app(OverlayData::class);

        $genderByKey = [];
        foreach ($data->categories($tid) as $c) {
            $name = mb_strtolower($c['category']['name'] ?? '');
            $g = (str_contains($name, 'moter') || str_contains($name, 'women') || str_contains($name, 'female')) ? 'M' : 'V';
            foreach ($data->participants($tid, (int) ($c['id'] ?? 0)) as $team) {
                foreach (explode(' / ', (string) ($team['name'] ?? '')) as $person) {
                    $person = trim($person);
                    if ($person !== '') {
                        $genderByKey[$data->personKey($person)] = $g;
                    }
                }
            }
        }

        $nationByKey = $data->peopleNations($tid); // personKey => „LT" (iš Tournated)

        $n = 0;
        foreach ($data->participantsPeople($tid) as $name) {
            $key = $data->personKey($name);
            $row = PlayerPhoto::firstOrNew(['tournament_external_id' => $tid, 'person_key' => $key]);
            $row->name = $name;
            if (! $row->exists) {
                $row->gender = $genderByKey[$key] ?? $defaultGender;
            }
            // Šalį įrašom tik jei dar tuščia — kad neperrašytume rankinio pakeitimo.
            if (empty($row->country) && ! empty($nationByKey[$key])) {
                $row->country = $nationByKey[$key];
            }
            $row->save();
            $n++;
        }

        return $n;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlayerPhotos::route('/'),
            'create' => Pages\CreatePlayerPhoto::route('/create'),
            'edit'   => Pages\EditPlayerPhoto::route('/{record}/edit'),
        ];
    }
}
