@php $tid = $slots[$key] ?? null; $name = $tid !== null ? $this->teamName($tid) : null; @endphp
<button type="button" wire:click="selectSlot('{{ $key }}')"
        @class([
            'w-full flex justify-between gap-2 px-3 py-2 text-sm text-left transition',
            'hover:bg-primary-50 dark:hover:bg-primary-500/10',
            'bg-primary-100 dark:bg-primary-500/20' => $this->selectedSlot === $key,
        ])>
    <span class="text-gray-400">{{ $key }}</span>
    <span @class(['font-medium', 'text-gray-400 italic' => $name === null, 'text-amber-600' => $name === 'BYE'])>
        {{ $name ?? '—' }}
    </span>
</button>
