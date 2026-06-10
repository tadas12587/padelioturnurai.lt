<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit" icon="heroicon-o-check">
                Išsaugoti nustatymus
            </x-filament::button>
        </div>
    </form>

    @php $health = $this->getSeoChecks(); @endphp

    <x-filament::section>
        <x-slot name="heading">SEO būklės įvertinimas</x-slot>
        <x-slot name="description">Automatinis patikrinimas, kaip sutvarkyta svetainės SEO.</x-slot>

        {{-- Score --}}
        <div class="flex items-center gap-4 mb-6">
            <div @class([
                'flex items-center justify-center w-20 h-20 rounded-full text-2xl font-black',
                'bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400' => $health['score'] >= 80,
                'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400' => $health['score'] >= 50 && $health['score'] < 80,
                'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-400' => $health['score'] < 50,
            ])>
                {{ $health['score'] }}
            </div>
            <div>
                <div class="font-bold text-lg">
                    @if($health['score'] >= 80) Puikiai sutvarkyta
                    @elseif($health['score'] >= 50) Gerai, bet yra ką patobulinti
                    @else Reikia dėmesio
                    @endif
                </div>
                <div class="text-sm text-gray-500">{{ $health['score'] }} / 100 balų</div>
            </div>
        </div>

        {{-- Checks list --}}
        <ul class="space-y-3">
            @foreach($health['checks'] as $check)
                <li class="flex items-start gap-3">
                    @if($check['status'] === 'ok')
                        <x-filament::icon icon="heroicon-o-check-circle" class="w-5 h-5 mt-0.5 text-success-500 shrink-0" />
                    @elseif($check['status'] === 'warn')
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="w-5 h-5 mt-0.5 text-warning-500 shrink-0" />
                    @else
                        <x-filament::icon icon="heroicon-o-x-circle" class="w-5 h-5 mt-0.5 text-danger-500 shrink-0" />
                    @endif
                    <div>
                        <span class="font-medium">{{ $check['label'] }}</span>
                        <span class="text-sm text-gray-500 dark:text-gray-400 block">{{ $check['note'] }}</span>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
