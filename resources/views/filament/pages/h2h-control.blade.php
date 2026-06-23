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
                <label class="text-sm font-medium">Akistatos langas</label>
                <select wire:model.live="windowId"
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                    <option value="">— Pasirink —</option>
                    @foreach($this->windowOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @php $overlay = $this->selectedOverlay(); @endphp

        @if($overlay)
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm text-gray-500">
                    OBS URL:
                    <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">{{ url('/overlay/' . $overlay->token) }}</code>
                </div>
                <x-filament::button wire:click="stop" color="gray" size="sm" icon="heroicon-o-stop">Sustabdyti</x-filament::button>
            </div>

            <input type="text" wire:model.live="search" placeholder="Ieškoti pagal žaidėją…"
                   class="block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">

            @php $active = $this->activeMatchId(); @endphp
            <div class="space-y-2 max-h-[28rem] overflow-y-auto">
                @forelse($this->matches() as $m)
                    <button type="button" wire:click="showMatch(@js($m['id']))"
                            @class([
                                'w-full text-left p-3 rounded-lg border transition',
                                'border-primary-500 bg-primary-50 dark:bg-primary-500/10' => (string)$active === (string)$m['id'],
                                'border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800' => (string)$active !== (string)$m['id'],
                            ])>
                        <div class="flex items-center justify-between gap-3">
                            <div class="text-sm">
                                <span class="font-medium">{{ implode(' / ', $m['team1']) ?: 'TBD' }}</span>
                                <span class="text-gray-400 mx-1">vs</span>
                                <span class="font-medium">{{ implode(' / ', $m['team2']) ?: 'TBD' }}</span>
                            </div>
                            <div class="text-xs text-gray-400 whitespace-nowrap">
                                @if($m['in_progress'])<span class="text-primary-600 font-bold">● LIVE</span> @endif
                                {{ trim(($m['time'] ?? '') . ' · ' . ($m['court'] ?? ''), ' ·') }}
                            </div>
                        </div>
                        @if($m['category'])<div class="text-xs text-gray-400 mt-1">{{ $m['category'] }}</div>@endif
                    </button>
                @empty
                    <div class="text-sm text-gray-500">Nėra rungtynių (patikrink ar push atsiuntė tvarkaraštį).</div>
                @endforelse
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
