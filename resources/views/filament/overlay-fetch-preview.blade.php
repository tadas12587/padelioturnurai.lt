@php
    $title = $info['title'] ?? null;
    $categories = $info['tournamentCategory'] ?? [];
@endphp

<div class="space-y-4 text-sm">
    @if(empty($info))
        <div class="rounded-lg bg-danger-50 dark:bg-danger-500/10 p-4 text-danger-700 dark:text-danger-400">
            <p class="font-semibold">Negauta duomenų.</p>
            <p class="mt-1">
                Turnyro ID <strong>{{ $id }}</strong> nieko negrąžino. Patikrink:
            </p>
            <ul class="mt-2 list-disc list-inside space-y-1">
                <li>ar teisingas Tournated turnyro ID;</li>
                <li>ar serveris pasiekia <code>api.tournated.com</code> (kartais shared hostingas blokuoja išeinančias užklausas);</li>
                <li>ar turnyras turi sukurtų kategorijų.</li>
            </ul>
        </div>
    @else
        <div>
            <div class="text-gray-500 dark:text-gray-400">Turnyras (ID {{ $id }}):</div>
            <div class="text-base font-bold">{{ $title ?: '— be pavadinimo —' }}</div>
        </div>

        @if(empty($categories))
            <div class="rounded-lg bg-warning-50 dark:bg-warning-500/10 p-4 text-warning-700 dark:text-warning-400">
                Turnyras rastas, bet jis neturi kategorijų.
            </div>
        @else
            <div>
                <div class="text-gray-500 dark:text-gray-400 mb-2">
                    Kategorijos ({{ count($categories) }}):
                </div>
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-1 pr-4">Kategorijos ID</th>
                            <th class="py-1 pr-4">Pavadinimas</th>
                            <th class="py-1">MDE</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categories as $cat)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-1 pr-4 font-mono">{{ $cat['id'] ?? '—' }}</td>
                                <td class="py-1 pr-4">{{ $cat['category']['name'] ?? '—' }}</td>
                                <td class="py-1">{{ $cat['mde'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="mt-3 text-gray-500 dark:text-gray-400">
                    Šias kategorijas galėsi pasirinkti „Overlay valdymas" puslapyje.
                </p>
            </div>
        @endif
    @endif
</div>
