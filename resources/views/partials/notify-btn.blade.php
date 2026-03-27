{{--
    Notify-me button + modal partial.
    Variables:
      $tournamentId   – Tournament ID (int)
      $tournamentName – Tournament title string
      $compact        – bool (optional) — smaller button for card list
--}}
@php
    $compact        = $compact ?? false;
    $uniqId         = 'notify_' . $tournamentId . '_' . Str::random(5);
    $storeUrl       = route('interest.store');
    $msgSuccess     = __('messages.notify_success');
    $msgAlready     = __('messages.notify_already');
    $msgError       = __('messages.notify_error');
    $msgSubmitting  = __('messages.notify_submitting');
@endphp

<div
    x-data="{
        open: false,
        name: '',
        email: '',
        loading: false,
        done: false,
        error: '',
        tournamentId: {{ $tournamentId }},
        storeUrl: '{{ $storeUrl }}',
        async submit() {
            if (!this.name.trim() || !this.email.trim()) return;
            this.loading = true;
            this.error = '';
            try {
                const res = await fetch(this.storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        tournament_id: this.tournamentId,
                        name: this.name,
                        email: this.email,
                    })
                });
                const json = await res.json();
                if (json.success) {
                    this.done = true;
                } else {
                    this.error = '{{ $msgError }}';
                }
            } catch (e) {
                this.error = '{{ $msgError }}';
            }
            this.loading = false;
        }
    }"
>
    {{-- Trigger button --}}
    @if($compact)
        <button
            @click="open = true"
            type="button"
            class="w-full flex items-center justify-center gap-2 py-2.5 border border-dark-border text-gray-400 hover:border-gold/60 hover:text-gold transition-colors text-xs font-semibold uppercase tracking-wide">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            {{ __('messages.notify_btn') }}
        </button>
    @else
        <button
            @click="open = true"
            type="button"
            class="inline-flex items-center gap-2 px-6 py-3 border border-gold/50 text-gold hover:border-gold hover:bg-gold/10 transition-colors font-semibold text-sm">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            {{ __('messages.notify_btn') }}
        </button>
    @endif

    {{-- Modal overlay --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="background: rgba(0,0,0,0.75);"
        @click.self="open = false; done = false; error = ''; name = ''; email = ''"
    >
        <div
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            class="bg-dark-card border border-dark-border w-full max-w-md p-8 relative"
            @click.stop
        >
            {{-- Close button --}}
            <button
                @click="open = false; done = false; error = ''; name = ''; email = ''"
                type="button"
                class="absolute top-4 right-4 text-gray-500 hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            {{-- Title --}}
            <div class="mb-6">
                <div class="text-gold text-xs tracking-[0.25em] uppercase font-semibold mb-2">
                    {{ Str::upper($tournamentName) }}
                </div>
                <h3 class="text-xl font-black text-white">{{ __('messages.notify_modal_title') }}</h3>
                <p class="text-gray-400 text-sm mt-2">{{ __('messages.notify_modal_sub') }}</p>
            </div>

            {{-- Success state --}}
            <div x-show="done" class="text-center py-6">
                <div class="w-16 h-16 rounded-full bg-gold/10 border border-gold/30 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <p class="text-white font-semibold text-lg">{{ $msgSuccess }}</p>
                <button
                    @click="open = false; done = false; name = ''; email = ''"
                    type="button"
                    class="mt-6 px-6 py-2 bg-gold text-dark font-bold text-sm hover:bg-gold-light transition-colors">
                    {{ app()->getLocale() === 'en' ? 'Close' : 'Uždaryti' }}
                </button>
            </div>

            {{-- Form --}}
            <form x-show="!done" @submit.prevent="submit()">
                <div class="space-y-4">
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-1">
                            {{ __('messages.notify_name_label') }}
                        </label>
                        <input
                            x-model="name"
                            type="text"
                            required
                            autocomplete="name"
                            placeholder="{{ app()->getLocale() === 'en' ? 'John Doe' : 'Jonas Jonaitis' }}"
                            class="w-full bg-dark border border-dark-border text-white px-4 py-3 text-sm focus:outline-none focus:border-gold transition-colors placeholder-gray-600">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-sm font-medium mb-1">
                            {{ __('messages.notify_email_label') }}
                        </label>
                        <input
                            x-model="email"
                            type="email"
                            required
                            autocomplete="email"
                            placeholder="el.pastas@gmail.com"
                            class="w-full bg-dark border border-dark-border text-white px-4 py-3 text-sm focus:outline-none focus:border-gold transition-colors placeholder-gray-600">
                    </div>
                </div>

                {{-- Error --}}
                <p x-show="error" x-text="error" class="text-red-400 text-sm mt-3"></p>

                {{-- Already registered hint --}}

                <button
                    type="submit"
                    :disabled="loading"
                    class="mt-6 w-full py-3 bg-gold text-dark font-bold text-sm hover:bg-gold-light transition-colors disabled:opacity-60">
                    <span x-show="!loading">{{ __('messages.notify_submit') }}</span>
                    <span x-show="loading">{{ $msgSubmitting }}</span>
                </button>

                <p class="text-gray-600 text-xs text-center mt-3">
                    {{ app()->getLocale() === 'en'
                        ? 'We will only use your email to notify you about this tournament registration.'
                        : 'Jūsų el. paštą naudosime tik pranešimui apie šio turnyro registraciją.' }}
                </p>
            </form>
        </div>
    </div>
</div>
