<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegistrationInterestResource\Pages;
use App\Models\RegistrationInterest;
use App\Models\Tournament;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Forms\Form;

class RegistrationInterestResource extends Resource
{
    protected static ?string $model = RegistrationInterest::class;

    protected static ?string $navigationIcon = 'heroicon-o-bell';

    protected static ?string $navigationLabel = 'Registracijos domėjimasis';

    protected static ?string $navigationGroup = 'Turnyrai';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tournament.id')
                    ->label('Turnyras')
                    ->formatStateUsing(function ($record) {
                        $trans = $record->tournament?->translations?->firstWhere('locale', 'lt')
                            ?? $record->tournament?->translations?->first();
                        return $trans?->title ?? $record->tournament?->slug ?? '—';
                    })
                    ->sortable()
                    ->searchable(false),

                TextColumn::make('name')
                    ->label('Vardas Pavardė')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('El. paštas')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Nukopijuota!'),

                TextColumn::make('locale')
                    ->label('Kalba')
                    ->badge()
                    ->color(fn($state) => $state === 'en' ? 'info' : 'success'),

                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('tournament_id')
                    ->label('Turnyras')
                    ->options(function () {
                        return Tournament::with('translations')
                            ->get()
                            ->mapWithKeys(function ($t) {
                                $trans = $t->translations->firstWhere('locale', 'lt')
                                    ?? $t->translations->first();
                                return [$t->id => $trans?->title ?? $t->slug];
                            });
                    }),
            ])
            ->groups([
                Group::make('tournament_id')
                    ->label('Turnyras')
                    ->getDescriptionFromRecordUsing(function ($record) {
                        $trans = $record->tournament?->translations?->firstWhere('locale', 'lt')
                            ?? $record->tournament?->translations?->first();
                        return $trans?->title ?? $record->tournament?->slug ?? 'Nenurodytas';
                    })
                    ->titlePrefixedWithLabel(false)
                    ->collapsible(),
            ])
            ->defaultGroup('tournament_id')
            ->defaultSort('created_at', 'desc')
            ->actions([
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegistrationInterests::route('/'),
        ];
    }
}
