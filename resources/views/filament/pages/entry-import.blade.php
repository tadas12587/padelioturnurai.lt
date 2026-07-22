<x-filament-panels::page>
    <div class="space-y-4 max-w-3xl">
        <p class="text-sm text-gray-500">
            Įkelk turnyro dalyvių Excel (.xlsx) — poros bus priskirtos kategorijoms ir naudojamos
            burtų traukimui, kol Tournated dar neturi rungtynių. Keliant iš naujo, sąrašas atsinaujina.
            Kategorijos sujungiamos pagal pavadinimą, tad jis turi sutapti su turnyro kategorijomis.
        </p>

        @forelse($this->imports() as $imp)
            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <div class="font-medium">Turnyras {{ $imp['tid'] }}
                        <span class="text-xs text-gray-400">· {{ $imp['total'] }} porų · {{ count($imp['cats']) }} kat.</span>
                    </div>
                    <div class="text-xs text-gray-400">{{ $imp['source'] }} · {{ $imp['when'] }}</div>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($imp['cats'] as $c)
                        <span class="text-xs px-2 py-1 rounded bg-gray-100 dark:bg-gray-800">
                            {{ $c['name'] }} <b>{{ $c['count'] }}</b>
                        </span>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="text-sm text-gray-500">Dar nieko neimportuota. Spausk „Importuoti Excel".</div>
        @endforelse
    </div>
</x-filament-panels::page>
