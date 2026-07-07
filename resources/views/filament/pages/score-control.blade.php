<x-filament-panels::page>
    <div class="space-y-6 max-w-3xl">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="text-sm font-medium">Pasirink overlay</label>
                <select wire:model.live="overlayId"
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                    <option value="">— Pasirink —</option>
                    @foreach($this->overlayOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium">Rezultato langas</label>
                <select wire:model.live="windowId"
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                    <option value="">— Pasirink —</option>
                    @foreach($this->windowOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @php $overlay = $this->selectedOverlay(); $s = $this->scoreState(); $active = $this->activeMatchId(); @endphp

        @if($overlay && $windowId)
            @if(empty($s['teams'] ?? []))
                <input type="text" wire:model.live="search" placeholder="Ieškoti rungtynių pagal žaidėją…"
                       class="block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                <div class="space-y-2 max-h-[28rem] overflow-y-auto">
                    @forelse($this->matches() as $m)
                        <button type="button" wire:click="selectMatch(@js($m['id']))"
                                class="w-full text-left p-3 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800">
                            <span class="font-medium">{{ implode(' / ', $m['team1']) ?: 'TBD' }}</span>
                            <span class="text-gray-400 mx-1">vs</span>
                            <span class="font-medium">{{ implode(' / ', $m['team2']) ?: 'TBD' }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ trim(($m['time'] ?? '') . ' · ' . ($m['court'] ?? ''), ' ·') }}</span>
                        </button>
                    @empty
                        <div class="text-sm text-gray-500">Nėra rungtynių (patikrink ar push atsiuntė tvarkaraštį).</div>
                    @endforelse
                </div>
            @else
                {{-- Live scoring --}}
                @php
                    $labels = ['0','15','30','40'];
                    $pt = function($t) use ($s, $labels) {
                        if (!empty($s['tiebreak'])) return $s['tb'][$t] ?? 0;
                        if (($s['star_stage'] ?? 0) === 'star') return '★';
                        if (($s['adv'] ?? null) === $t) return ($s['star_stage'] ?? 0) === 'adv1' ? '1AD' : ((($s['star_stage'] ?? 0) === 'adv2') ? '2AD' : 'AD');
                        return $labels[min((int)($s['points'][$t] ?? 0), 3)];
                    };
                @endphp
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-500">OBS URL:
                        <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">{{ url('/overlay/' . $overlay->token) }}</code>
                    </div>
                    <div class="flex gap-2">
                        <x-filament::button wire:click="resetScore" color="danger" size="sm" icon="heroicon-o-trash">Iš naujo</x-filament::button>
                        <x-filament::button wire:click="stop" color="gray" size="sm" icon="heroicon-o-stop">Sustabdyti</x-filament::button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    @foreach([0,1] as $t)
                        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 text-center space-y-3
                            {{ ($s['server_team'] ?? 0) === $t ? 'ring-2 ring-primary-500' : '' }}">
                            <div class="font-medium">
                                {{ implode(' / ', array_map(fn($n) => app(\App\Services\OverlayData::class)->abbrevName($n), $s['teams'][$t] ?? [])) }}
                            </div>
                            <div class="text-4xl font-bold font-mono">{{ $pt($t) }}</div>
                            <div class="text-sm text-gray-400">
                                Geimai: {{ $s['games'][$t] ?? 0 }}
                                @if(!empty($s['sets'])) · Setai: {{ implode(' ', array_map(fn($x)=>$x[$t], $s['sets'])) }} @endif
                            </div>
                            <div class="flex justify-center gap-2">
                                <x-filament::button wire:click="point({{ $t }})" size="lg" color="success">+ taškas</x-filament::button>
                                <x-filament::button wire:click="undo" size="lg" color="gray">−</x-filament::button>
                            </div>
                            <x-filament::button wire:click="game({{ $t }})" size="sm" color="success" outlined icon="heroicon-o-forward">+ geimas</x-filament::button>
                            <x-filament::button wire:click="setServer({{ $t }})" size="sm" color="gray" icon="heroicon-o-play">Servas šiai</x-filament::button>
                        </div>
                    @endforeach
                </div>

                @if(($s['status'] ?? '') === 'finished')
                    <div class="text-center font-bold text-primary-600">Mačas baigtas — laimėjo {{ implode(' / ', array_map(fn($n)=>app(\App\Services\OverlayData::class)->abbrevName($n), $s['teams'][$s['winner']] ?? [])) }}</div>
                @endif
                @if(!empty($s['tiebreak']))
                    <div class="text-center text-sm text-primary-600">{{ !empty($s['super_tiebreak']) ? 'Super tiebreak' : 'Tiebreak' }}</div>
                @endif
            @endif
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
