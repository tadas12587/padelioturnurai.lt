<x-filament-panels::page>
    <div class="space-y-6 max-w-4xl">
        {{-- Overlay + window selectors --}}
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
                <label class="text-sm font-medium">Traukimo langas</label>
                <select wire:model.live="windowId"
                        class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                    <option value="">— Pasirink —</option>
                    @foreach($this->windowOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @php $overlay = $this->selectedOverlay(); $window = $this->currentWindow(); $s = $this->drawState(); @endphp

        @if($overlay && $window)
            <div class="text-sm text-gray-500">
                OBS URL:
                <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">{{ url('/overlay/' . $overlay->token) }}</code>
            </div>

            {{-- Load participants + Play/Stop --}}
            <div class="flex flex-wrap gap-2">
                <x-filament::button wire:click="loadParticipants" color="gray" icon="heroicon-o-arrow-down-tray">
                    Užkrauti dalyvius iš Tournated
                </x-filament::button>
                <x-filament::button wire:click="play" icon="heroicon-o-play">Rodyti (OBS)</x-filament::button>
                <x-filament::button wire:click="stop" color="gray" icon="heroicon-o-stop">Sustabdyti</x-filament::button>
            </div>

            @if(empty($s['teams'] ?? []))
                <div class="text-sm text-amber-600">Dar neužkrauti dalyviai. Paspausk „Užkrauti dalyvius iš Tournated"
                    (arba patikrink, ar push.js atsiuntė šios kategorijos dalyvius).</div>
            @else
                {{-- Draw controls --}}
                <div class="flex flex-wrap items-center gap-3 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                    <x-filament::button wire:click="drawNext" size="lg" icon="heroicon-o-sparkles">
                        TRAUKTI
                    </x-filament::button>
                    @if(($s['status'] ?? 'idle') === 'done')
                        <span class="font-bold text-primary-600">● Traukimas baigtas</span>
                    @else
                        <span class="text-sm">Krepšelis <span class="font-bold">{{ $s['active_pot'] ?? 1 }}</span></span>
                    @endif
                    <div class="ml-auto flex gap-2">
                        <x-filament::button wire:click="undo" color="gray" size="sm" icon="heroicon-o-arrow-uturn-left">Atšaukti</x-filament::button>
                        <x-filament::button wire:click="resetBoard" color="danger" size="sm" icon="heroicon-o-trash">Iš naujo</x-filament::button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Manual placement --}}
                    <div class="space-y-3">
                        <div class="text-sm font-medium">Įdėti rankiniu būdu</div>
                        <select wire:model.live="manualSlot"
                                class="block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                            <option value="">— Pasirink tuščią vietą —</option>
                            @foreach($this->emptySlots() as $slot)
                                <option value="{{ $slot }}">{{ $slot }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model.live="search" placeholder="Ieškoti komandos…"
                               class="block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                        <div class="max-h-72 overflow-y-auto space-y-1">
                            @forelse($this->remainingTeams() as $t)
                                <button type="button" wire:click="placeManual({{ $t['id'] }})"
                                        class="w-full text-left px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-primary-50 dark:hover:bg-primary-500/10">
                                    {{ $t['name'] }}
                                    @if(!empty($t['seed'])) <span class="text-xs text-gray-400">(sėkla {{ $t['seed'] }})</span>
                                    @elseif(!empty($t['pot'])) <span class="text-xs text-gray-400">(krepšelis {{ $t['pot'] }})</span>
                                    @endif
                                </button>
                            @empty
                                <div class="text-sm text-gray-500">Nėra likusių komandų.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Mini board preview --}}
                    <div class="space-y-2">
                        <div class="text-sm font-medium">Lentos peržiūra</div>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach(($s['slots'] ?? []) as $key => $tid)
                                <div class="flex justify-between gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-sm">
                                    <span class="text-gray-400">{{ $key }}</span>
                                    <span class="font-medium">{{ $tid ? $this->teamName($tid) : '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
