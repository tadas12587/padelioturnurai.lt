<x-filament-panels::page>
    <div class="space-y-6 max-w-2xl">
        <p class="text-sm text-gray-500">
            Parsisiųsk įrankį į kompiuterį, iš kurio transliuosi, ir paleisk. Jis automatiškai
            siunčia turnyro duomenis į svetainę — Node.js diegti nereikia. Programą laikyk
            paleistą visą transliacijos laiką.
        </p>

        <div class="space-y-3">
            @foreach($this->downloads() as $d)
                <div class="flex items-center justify-between gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-700">
                    <span class="font-medium">{{ $d['label'] }}</span>
                    @if($d['exists'])
                        <x-filament::button tag="a" href="{{ $d['url'] }}" icon="heroicon-o-arrow-down-tray">
                            Atsisiųsti
                        </x-filament::button>
                    @else
                        <span class="text-sm text-amber-600">dar neįkelta</span>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="text-sm space-y-2">
            <div class="font-medium">Kaip paleisti</div>
            <ul class="list-disc pl-5 space-y-1 text-gray-600 dark:text-gray-400">
                <li><b>Windows:</b> dukart spustelėk atsisiųstą <code>.exe</code>. Jei „Windows protected your PC" — „More info" → „Run anyway".</li>
                <li><b>Mac:</b> pirmą kartą — dešinys pelės mygtukas ant failo → „Open" → „Open" (nes programa nepasirašyta). Vėliau dukart spustelėk.</li>
            </ul>
        </div>

        <div class="text-sm text-gray-500">
            Aktyvūs turnyrai: <b>{{ implode(', ', $this->tournaments()) ?: '—' }}</b>
        </div>
    </div>
</x-filament-panels::page>
