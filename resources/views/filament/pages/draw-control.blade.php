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

                @php $layout = $this->layout(); $slots = $s['slots'] ?? []; @endphp

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Editable player pool --}}
                    <div class="space-y-3">
                        <div class="text-sm font-medium">Galimi žaidėjai ({{ count($this->allTeams()) }})</div>
                        <div class="flex gap-2">
                            <input type="text" wire:model="newTeamName" wire:keydown.enter="addTeam"
                                   placeholder="Pridėti komandą (Vardas / Vardas)"
                                   class="block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                            <x-filament::button wire:click="addTeam" color="gray" icon="heroicon-o-plus">Pridėti</x-filament::button>
                        </div>
                        <div class="max-h-96 overflow-y-auto space-y-1">
                            @forelse($this->allTeams() as $t)
                                @php $tid = (string) $t['id']; $isPlaced = in_array($t['id'], array_values($slots)); @endphp
                                <div class="flex items-center gap-2 px-2 py-1 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <input type="text" value="{{ $t['name'] }}"
                                           wire:change="renameTeam('{{ $tid }}', $event.target.value)"
                                           class="flex-1 bg-transparent border-0 text-sm focus:ring-0 p-1">
                                    @if($isPlaced)<span class="text-xs text-primary-500">●</span>@endif
                                    <button type="button" wire:click="removeTeam('{{ $tid }}')"
                                            class="text-gray-400 hover:text-danger-500" title="Pašalinti">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            @empty
                                <div class="text-sm text-gray-500">Nėra žaidėjų. Užkrauk iš Tournated arba pridėk ranka.</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Clickable board --}}
                    <div class="space-y-2">
                        <div class="text-sm font-medium">Lenta — spustelėk vietą</div>

                        @if($layout['format'] === 'bracket')
                            <div class="space-y-2">
                                @foreach($layout['pairs'] as $pair)
                                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 divide-y dark:divide-gray-700">
                                        @foreach($pair as $key)
                                            @include('filament.pages.partials.draw-slot', ['key' => $key, 'slots' => $slots])
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($layout['groups'] as $g)
                                    <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
                                        <div class="px-3 py-1 text-xs font-semibold bg-gray-50 dark:bg-gray-800">Grupė {{ $g['label'] }}</div>
                                        @foreach($g['slots'] as $key)
                                            @include('filament.pages.partials.draw-slot', ['key' => $key, 'slots' => $slots])
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Slot picker popup: opens when a free slot is clicked --}}
                @if($this->selectedSlot)
                    <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
                         wire:click.self="cancelSelect" wire:key="picker-{{ $this->selectedSlot }}">
                        <div class="w-full max-w-xl rounded-xl bg-white dark:bg-gray-900 shadow-2xl ring-1 ring-gray-200 dark:ring-gray-700 p-5 space-y-3">
                            <div class="flex items-center justify-between">
                                <div class="text-base font-semibold">Į vietą <span class="text-primary-600">{{ $this->selectedSlot }}</span> įdėti:</div>
                                <button type="button" wire:click="cancelSelect" class="text-gray-400 hover:text-gray-600">
                                    @svg('heroicon-o-x-mark', 'w-6 h-6')
                                </button>
                            </div>
                            <input type="text" wire:model.live="search" placeholder="Ieškoti komandos…" autofocus
                                   class="block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                            <div class="flex flex-wrap gap-2 max-h-[60vh] overflow-y-auto">
                                <button type="button" wire:click="placeBye"
                                        class="px-3 py-2 rounded-lg border border-amber-400 text-amber-700 dark:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-500/10 font-medium">
                                    BYE
                                </button>
                                @forelse($this->remainingTeams() as $t)
                                    <button type="button" wire:click="placeTeam('{{ (string) $t['id'] }}')"
                                            class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-primary-50 dark:hover:bg-primary-500/10 text-sm">
                                        {{ $t['name'] }}
                                    </button>
                                @empty
                                    <div class="text-sm text-gray-500">Nėra likusių komandų (gali įdėti BYE).</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
