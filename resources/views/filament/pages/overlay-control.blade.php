<x-filament-panels::page>
    <div class="space-y-6 max-w-2xl">
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
            {{-- Output URL --}}
            <div class="text-sm text-gray-500">
                OBS URL:
                <code class="px-2 py-1 bg-gray-100 dark:bg-gray-800 rounded">{{ url('/overlay/' . $overlay->token) }}</code>
            </div>

            @if($overlay->type === 'group_standings')
                <div>
                    <label class="text-sm font-medium">Kategorija</label>
                    <select wire:model.live="data.active_category_id"
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                        <option value="">— Pasirink kategoriją —</option>
                        @foreach($this->categoryOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium">Pogrupis</label>
                    <select wire:model="data.active_group_id"
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600">
                        @foreach($this->groupOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="text-sm font-medium">„Kitas susitikimas" tekstas</label>
                <input type="text" wire:model="data.next_match"
                       class="mt-1 block w-full rounded-lg border-gray-300 dark:bg-gray-800 dark:border-gray-600"
                       placeholder="Pvz. Toliau: Jonai / Petrai vs Antanai / Kazys">
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" wire:model="data.visible" class="rounded">
                <span class="text-sm font-medium">Rodyti overlay (matoma)</span>
            </label>

            <x-filament::button wire:click="apply" icon="heroicon-o-check">
                Pritaikyti
            </x-filament::button>
        @endif
    </div>
</x-filament-panels::page>
