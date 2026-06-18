<?php

namespace App\Filament\Pages;

use App\Models\Overlay;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class BroadcasterToolPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationGroup = 'Transliacijos';
    protected static ?string $navigationLabel = 'Transliacijos įrankis';
    protected static ?string $title = 'Transliacijos įrankis';
    protected static string $view = 'filament.pages.broadcaster-tool';

    /** @return list<array{os:string,label:string,file:string,url:?string,exists:bool}> */
    public function downloads(): array
    {
        $items = [
            ['os' => 'win', 'label' => 'Windows', 'file' => 'broadcaster/overlay-push-win.exe'],
            ['os' => 'mac-arm', 'label' => 'Mac (Apple Silicon)', 'file' => 'broadcaster/overlay-push-mac-arm'],
            ['os' => 'mac-intel', 'label' => 'Mac (Intel)', 'file' => 'broadcaster/overlay-push-mac-intel'],
        ];

        return array_map(function ($i) {
            $i['exists'] = Storage::disk('public')->exists($i['file']);
            $i['url'] = $i['exists'] ? Storage::disk('public')->url($i['file']) : null;

            return $i;
        }, $items);
    }

    public function token(): ?string
    {
        return config('services.overlay.ingest_token');
    }

    /** @return list<string> */
    public function tournaments(): array
    {
        return Overlay::query()
            ->whereNotNull('tournament_external_id')
            ->where('tournament_external_id', '!=', '')
            ->pluck('tournament_external_id')
            ->map(fn ($i) => (string) $i)
            ->unique()->values()->all();
    }
}
