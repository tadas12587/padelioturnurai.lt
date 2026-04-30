<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactResource\Pages;
use App\Models\Contact;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $navigationLabel = 'Žinutės';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Vardas')
                    ->disabled(),

                TextInput::make('email')
                    ->label('El. paštas')
                    ->disabled(),

                Textarea::make('message')
                    ->label('Žinutė')
                    ->rows(5)
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('read_at')
                    ->label('')
                    ->icon(fn ($record) => $record->read_at ? 'heroicon-o-envelope-open' : 'heroicon-o-envelope')
                    ->color(fn ($record) => $record->read_at ? 'gray' : 'warning')
                    ->tooltip(fn ($record) => $record->read_at ? 'Perskaityta' : 'Neperskaityta')
                    ->width('40px'),

                TextColumn::make('name')
                    ->label('Vardas')
                    ->searchable()
                    ->weight(fn ($record) => $record->read_at ? 'normal' : 'bold'),

                TextColumn::make('email')
                    ->label('El. paštas')
                    ->searchable(),

                TextColumn::make('message')
                    ->label('Žinutė')
                    ->limit(50),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_as_read')
                        ->label('Žymėti kaip perskaityta')
                        ->icon('heroicon-o-envelope-open')
                        ->action(fn (Collection $records) => $records->each(
                            fn ($record) => $record->update(['read_at' => Carbon::now()])
                        ))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_as_unread')
                        ->label('Žymėti kaip neperskaityta')
                        ->icon('heroicon-o-envelope')
                        ->action(fn (Collection $records) => $records->each(
                            fn ($record) => $record->update(['read_at' => null])
                        ))
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->label('Trinti pasirinktas'),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContacts::route('/'),
            'view' => Pages\ViewContact::route('/{record}'),
        ];
    }
}
