<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PlayerPhotoResource;
use App\Models\EntryList;
use App\Models\Overlay;
use App\Services\EntryListImporter;
use App\Services\OverlayData;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class EntryImportPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Dalyvių importas (Excel)';
    protected static ?string $title = 'Dalyvių importas iš Excel';
    protected static string $view = 'filament.pages.entry-import';

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('import')
                ->label('Importuoti Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\Select::make('tid')
                        ->label('Turnyras')
                        ->options(fn () => PlayerPhotoResource::tournamentOptions())
                        ->searchable()->required(),
                    Forms\Components\FileUpload::make('file')
                        ->label('Excel failas (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->disk('local')->directory('entry-imports')->required()
                        ->helperText('Stulpeliai: Category, Player 1/2 Name/Surname, Seed. Keliant iš naujo — sąrašas atsinaujina.'),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);
                    try {
                        $res = EntryListImporter::import($data['tid'], $path, basename($data['file']));
                    } catch (\Throwable $e) {
                        Notification::make()->title('Klaida: ' . $e->getMessage())->danger()->send();

                        return;
                    } finally {
                        Storage::disk('local')->delete($data['file']);
                    }

                    $unmatched = $this->unmatchedCategories($data['tid'], $res['names']);
                    $msg = "Importuota {$res['total']} porų, " . count($res['by_cat']) . ' kategorijų.';
                    if ($unmatched) {
                        $msg .= ' ⚠ Nesutampa su turnyro kategorijomis: ' . implode('; ', $unmatched);
                    }
                    Notification::make()->title($msg)->success()->send();
                }),
        ];
    }

    /** Imported lists, for the page body. @return list<array<string,mixed>> */
    public function imports(): array
    {
        return EntryList::orderByDesc('updated_at')->get()->map(function (EntryList $e) {
            $data = $e->data ?? [];
            $cats = [];
            foreach ($data as $norm => $teams) {
                $cats[] = ['name' => $norm, 'count' => count($teams)];
            }

            return [
                'tid'    => $e->tournament_external_id,
                'source' => $e->source_name,
                'total'  => array_sum(array_map('count', $data)),
                'cats'   => $cats,
                'when'   => $e->updated_at?->format('Y-m-d H:i'),
            ];
        })->all();
    }

    /** Category names from the file that don't match the tournament's categories. */
    private function unmatchedCategories(string $tid, array $names): array
    {
        $known = [];
        foreach (app(OverlayData::class)->categories($tid) as $c) {
            $known[EntryList::normCategory((string) ($c['category']['name'] ?? ''))] = true;
        }
        $out = [];
        foreach ($names as $norm => $display) {
            if (! isset($known[$norm])) {
                $out[] = $display;
            }
        }

        return $out;
    }
}
