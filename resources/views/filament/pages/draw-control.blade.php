<x-filament-panels::page>
    <div class="mx-auto max-w-5xl space-y-5">

        {{-- 1 · Kur transliuojam --}}
        <section class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">1 · Overlay</label>
                    <select wire:model.live="overlayId"
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                        <option value="">— Pasirink —</option>
                        @foreach($this->overlayOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500">2 · Traukimo langas</label>
                    <select wire:model.live="windowId"
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                        <option value="">— Pasirink —</option>
                        @foreach($this->windowOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        @php $overlay = $this->selectedOverlay(); $window = $this->currentWindow(); $s = $this->drawState(); @endphp

        @if($overlay && $window)
            @php $live = $this->isLive(); $prog = $this->progress(); $pct = $prog['total'] ? round($prog['placed'] / $prog['total'] * 100) : 0; @endphp

            {{-- 2 · Būsena + OBS valdymas --}}
            <section class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 flex flex-wrap items-center gap-3">
                <span @class([
                    'inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-sm font-semibold',
                    'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-400' => $live,
                    'bg-gray-100 text-gray-500 dark:bg-white/10' => ! $live,
                ])>
                    <span @class(['w-2 h-2 rounded-full', 'bg-success-500 animate-pulse' => $live, 'bg-gray-400' => ! $live])></span>
                    {{ $live ? 'TIESIOGIAI (OBS)' : 'Nerodoma' }}
                </span>

                <span class="text-xs text-gray-400 truncate">OBS: <code class="px-1.5 py-0.5 bg-gray-100 dark:bg-white/10 rounded">{{ url('/overlay/' . $overlay->token) }}</code></span>

                <div class="ml-auto flex flex-wrap gap-2">
                    <x-filament::button wire:click="loadParticipants" color="gray" size="sm" icon="heroicon-o-arrow-down-tray">
                        Užkrauti dalyvius
                    </x-filament::button>
                    @if($live)
                        <x-filament::button wire:click="stop" color="gray" size="sm" icon="heroicon-o-stop">Sustabdyti</x-filament::button>
                    @else
                        <x-filament::button wire:click="play" color="success" size="sm" icon="heroicon-o-play">Rodyti (OBS)</x-filament::button>
                    @endif
                </div>
            </section>

            @if(empty($s['teams'] ?? []))
                <div class="rounded-xl border border-amber-300/60 bg-amber-50 dark:bg-amber-500/10 dark:border-amber-500/30 p-5 text-center">
                    <div class="text-amber-800 dark:text-amber-300 font-medium">Dar neužkrauti dalyviai</div>
                    <p class="mt-1 text-sm text-amber-700/80 dark:text-amber-300/70">
                        Paspausk <b>„Užkrauti dalyvius"</b> — jie ateis iš Tournated arba iš importuoto Excel.
                        Gali ir pridėti ranka žemiau.
                    </p>
                </div>
            @endif

            {{-- 3 · Traukimas --}}
            <section class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 space-y-3">
                <div class="flex flex-wrap items-center gap-4">
                    <x-filament::button wire:click="drawNext" size="xl" icon="heroicon-o-sparkles"
                        :disabled="empty($s['teams'] ?? []) || ($s['status'] ?? '') === 'done'">
                        TRAUKTI
                    </x-filament::button>

                    @if(($s['status'] ?? 'idle') === 'done')
                        <span class="inline-flex items-center gap-2 font-bold text-success-600">
                            @svg('heroicon-o-check-circle', 'w-6 h-6') Traukimas baigtas
                        </span>
                    @else
                        <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-50 dark:bg-primary-500/15 text-primary-700 dark:text-primary-300 text-sm font-semibold">
                            Krepšelis {{ $s['active_pot'] ?? 1 }}
                        </span>
                    @endif

                    <div class="ml-auto flex gap-2">
                        <x-filament::button wire:click="undo" color="gray" size="sm" icon="heroicon-o-arrow-uturn-left">Atšaukti</x-filament::button>
                        <x-filament::button wire:click="resetBoard" color="danger" size="sm" icon="heroicon-o-trash">Iš naujo</x-filament::button>
                    </div>
                </div>

                {{-- Progresas --}}
                <div>
                    <div class="flex justify-between text-xs text-gray-500 mb-1">
                        <span>Ištraukta</span>
                        <span>{{ $prog['placed'] }} / {{ $prog['total'] }}</span>
                    </div>
                    <div class="h-2 rounded-full bg-gray-200 dark:bg-white/10 overflow-hidden">
                        <div class="h-2 rounded-full bg-primary-500 transition-all duration-500" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            </section>

            @php $layout = $this->layout(); $slots = $s['slots'] ?? []; $placedIds = array_values(array_filter($slots)); @endphp

            @unless(empty($s['teams'] ?? []))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    {{-- Žaidėjai --}}
                    <section class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="text-sm font-semibold">Žaidėjai</div>
                            <div class="text-xs text-gray-500">
                                Liko <b class="text-primary-600">{{ max(0, count($this->allTeams()) - count($placedIds)) }}</b> · iš {{ count($this->allTeams()) }}
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" wire:model="newTeamName" wire:keydown.enter="addTeam"
                                   placeholder="Pridėti porą (Vardas / Vardas)"
                                   class="block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600 text-sm">
                            <x-filament::button wire:click="addTeam" color="gray" icon="heroicon-o-plus">Pridėti</x-filament::button>
                        </div>
                        <div class="max-h-[26rem] overflow-y-auto space-y-1 pr-1">
                            @forelse($this->allTeams() as $t)
                                @php $tid = (string) $t['id']; $isPlaced = in_array($t['id'], $placedIds); @endphp
                                <div @class([
                                    'flex items-center gap-2 px-2 py-1 rounded-lg border transition',
                                    'border-primary-200 bg-primary-50/50 dark:border-primary-500/20 dark:bg-primary-500/5' => $isPlaced,
                                    'border-gray-200 dark:border-white/10' => ! $isPlaced,
                                ])>
                                    @if($isPlaced)
                                        <span class="text-primary-500" title="Jau ištraukta">@svg('heroicon-s-check-circle', 'w-4 h-4')</span>
                                    @else
                                        <span class="w-4 h-4 rounded-full border border-gray-300 dark:border-gray-600 flex-none"></span>
                                    @endif
                                    <input type="text" value="{{ $t['name'] }}"
                                           wire:change="renameTeam('{{ $tid }}', $event.target.value)"
                                           class="flex-1 bg-transparent border-0 text-sm focus:ring-0 p-1">
                                    <button type="button" wire:click="removeTeam('{{ $tid }}')"
                                            class="text-gray-300 hover:text-danger-500" title="Pašalinti">
                                        @svg('heroicon-o-trash', 'w-4 h-4')
                                    </button>
                                </div>
                            @empty
                                <div class="text-sm text-gray-500">Nėra žaidėjų. Užkrauk arba pridėk ranka.</div>
                            @endforelse
                        </div>
                    </section>

                    {{-- Lenta --}}
                    <section class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-white/5 p-4 space-y-2">
                        <div class="text-sm font-semibold">Lenta <span class="font-normal text-gray-400">— spustelėk laisvą vietą</span></div>

                        @if(($layout['format'] ?? '') === 'bracket')
                            <div class="space-y-2">
                                @foreach($layout['pairs'] as $pair)
                                    <div class="rounded-lg border border-gray-200 dark:border-white/10 divide-y dark:divide-white/10">
                                        @foreach($pair as $key)
                                            @include('filament.pages.partials.draw-slot', ['key' => $key, 'slots' => $slots])
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($layout['groups'] ?? [] as $g)
                                    <div class="rounded-lg border border-gray-200 dark:border-white/10 overflow-hidden">
                                        <div class="px-3 py-1 text-xs font-semibold bg-gray-50 dark:bg-white/5">Grupė {{ $g['label'] }}</div>
                                        @foreach($g['slots'] as $key)
                                            @include('filament.pages.partials.draw-slot', ['key' => $key, 'slots' => $slots])
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                </div>
            @endunless

            {{-- Slot picker popup --}}
            @if($this->selectedSlot)
                <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
                     wire:click.self="cancelSelect" wire:key="picker-{{ $this->selectedSlot }}">
                    <div class="w-full max-w-xl rounded-2xl bg-white dark:bg-gray-900 shadow-2xl ring-1 ring-gray-200 dark:ring-white/10 p-5 space-y-3">
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
                                        class="px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 hover:bg-primary-50 dark:hover:bg-primary-500/10 text-sm">
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
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
