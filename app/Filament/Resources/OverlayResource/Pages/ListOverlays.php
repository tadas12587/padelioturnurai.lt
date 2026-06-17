<?php

namespace App\Filament\Resources\OverlayResource\Pages;

use App\Filament\Resources\OverlayResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;

class ListOverlays extends ListRecords
{
    protected static string $resource = OverlayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pushHelp')
                ->label('Kaip paleisti duomenų siuntimą')
                ->icon('heroicon-o-command-line')
                ->color('gray')
                ->modalHeading('Duomenų siuntimas (push)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Uždaryti')
                ->modalContent(fn () => new HtmlString(
                    '<div class="space-y-3 text-sm">'
                    . '<p>Kad overlay\'ai gautų gyvus turnyro duomenis (grupes, bracketus, rezultatus), '
                    . 'savo kompiuteryje paleisk „push" scenarijų ir palik jį atidarytą visą transliaciją.</p>'
                    . '<p class="font-medium">PowerShell:</p>'
                    . '<pre class="rounded-lg bg-gray-900 text-gray-100 p-3 overflow-x-auto"><code>'
                    . 'cd C:\Users\Tadas\Desktop\WEB-zinovai\tools\overlay-push' . "\n" . 'node push.js'
                    . '</code></pre>'
                    . '<p>Pamatysi <code>✅ Nusiųsta...</code> kas ~20 s. Uždarius langą (Ctrl+C) — '
                    . 'duomenys nustoja atsinaujinti.</p>'
                    . '<p class="text-gray-500">Perpaleidimui: senajame lange Ctrl+C, tada komandą iš naujo '
                    . '(arba <code>Get-Process node | Stop-Process -Force</code> ir vėl <code>node push.js</code>).</p>'
                    . '</div>'
                )),

            Actions\CreateAction::make(),
        ];
    }
}
