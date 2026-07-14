<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GalleryResource\Pages;
use App\Models\Gallery;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static ?string $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationGroup = 'Transliacijos';

    protected static ?string $navigationLabel = 'Galerijos';

    protected static ?string $modelLabel = 'Galerija';

    protected static ?string $pluralModelLabel = 'Galerijos';

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')
                ->label('Pavadinimas')
                ->required()
                ->helperText('Pvz. „Turnyro X rėmėjai" — pagal šį pavadinimą galeriją rinksies languose.'),

            FileUpload::make('images')
                ->label('Nuotraukos')
                ->image()->multiple()->reorderable()->appendFiles()
                ->disk('public')->directory('galleries')
                // Removing a photo here deletes the file from the server too.
                ->deleteUploadedFileUsing(fn (string $file) => Storage::disk('public')->delete($file))
                ->helperText('Įkelk kelias nuotraukas iškart; vilkdamas keisk eiliškumą. Pašalinus nuotrauką — ji ištrinama ir iš serverio.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Pavadinimas')->searchable()->sortable(),
                TextColumn::make('images')->label('Nuotraukų')
                    ->state(fn (Gallery $r) => count($r->imagePaths()))->badge(),
                TextColumn::make('updated_at')->label('Atnaujinta')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGalleries::route('/'),
            'create' => Pages\CreateGallery::route('/create'),
            'edit'   => Pages\EditGallery::route('/{record}/edit'),
        ];
    }
}
