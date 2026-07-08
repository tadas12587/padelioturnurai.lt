<x-filament-panels::page>
    <div class="space-y-6 max-w-3xl">
        {{-- Overlay selector --}}
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

        @php $overlay = $this->selectedOverlay(); @endphp

        @if($overlay)
            <div class="text-sm text-gray-500">
                OBS URL:
                <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">{{ url('/overlay/' . $overlay->token) }}</code>
            </div>

            {{-- Stop --}}
            <div>
                <x-filament::button wire:click="stop" color="gray" icon="heroicon-o-stop">
                    Sustabdyti viską
                </x-filament::button>
            </div>

            {{-- Windows — kelis galima rodyti vienu metu --}}
            <div class="space-y-2">
                <div class="text-sm font-medium">Langai <span class="text-xs text-gray-400">(kelis galima rodyti vienu metu)</span></div>
                @forelse($overlay->windows ?? [] as $w)
                    @php $shown = $this->isShown($w['id'] ?? ''); @endphp
                    <div class="flex items-center justify-between gap-3 p-3 rounded-lg border
                        {{ $shown ? 'border-primary-500 bg-primary-50 dark:bg-primary-500/10' : 'border-gray-200 dark:border-gray-700' }}">
                        <div class="flex items-center gap-2">
                            <span class="font-medium">{{ $w['name'] ?? 'Langas' }}</span>
                            <span class="text-xs text-gray-400">{{ $w['type'] ?? 'groups' }}</span>
                            @if($shown)
                                <span class="text-xs font-bold text-primary-600 dark:text-primary-400">● GYVAI</span>
                            @endif
                        </div>
                        @if($shown)
                            <x-filament::button size="sm" color="gray" wire:click="hide('{{ $w['id'] }}')" icon="heroicon-o-eye-slash">
                                Slėpti
                            </x-filament::button>
                        @else
                            <x-filament::button size="sm" wire:click="play('{{ $w['id'] }}')" icon="heroicon-o-play">
                                Rodyti
                            </x-filament::button>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-gray-500">Šis overlay neturi langų. Sukurk juos overlay redagavimo lange.</div>
                @endforelse
            </div>

            {{-- Next match lower-third --}}
            <div x-data="{ txt: @js($overlay->state['next_match'] ?? '') }">
                <label class="text-sm font-medium">„Kitas susitikimas" juosta</label>
                <div class="flex gap-2 mt-1">
                    <input type="text" x-model="txt"
                           class="block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600"
                           placeholder="Pvz. Toliau: Jonai / Petrai vs Antanai / Kazys">
                    <x-filament::button color="gray" x-on:click="$wire.setNextMatch(txt)">
                        Atnaujinti
                    </x-filament::button>
                </div>
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
