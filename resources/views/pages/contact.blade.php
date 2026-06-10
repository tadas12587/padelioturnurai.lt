@extends('layouts.app')

@section('title', 'Padelio Turnyrai - ' . __('messages.nav_contact'))

@section('content')
<div class="pt-24 pb-16 bg-dark min-h-screen">
    <div class="max-w-2xl mx-auto px-4">
        {{-- Header --}}
        <div class="py-16 text-center" data-aos="fade-up">
            <div class="text-gold text-sm tracking-[0.3em] uppercase font-semibold mb-4">{{ __('messages.nav_contact') }}</div>
            <h1 class="text-5xl font-black text-white">{{ __('messages.contact_title') }}</h1>
        </div>

        {{-- Success message --}}
        @if(session('success'))
            <div class="mb-8 p-6 bg-green-500/10 border border-green-500/30 text-green-400 text-center" data-aos="fade-up">
                {{ __('messages.contact_success') }}
            </div>
        @endif

        {{-- Contact Form --}}
        <div class="bg-dark-card border border-dark-border p-8 md:p-12" data-aos="fade-up" data-aos-delay="100">
            <form action="{{ lroute('contact.store') }}" method="POST" class="space-y-6" id="contactForm" x-data="{ sending: false }" @submit="sending = true">
                @csrf

                {{-- Name --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-400 mb-2 tracking-wide uppercase">
                        {{ __('messages.contact_name') }}
                    </label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                           class="w-full bg-dark border border-dark-border text-white px-4 py-3 focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold transition-colors placeholder-gray-600"
                           placeholder="{{ __('messages.contact_name') }}">
                    @error('name')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-400 mb-2 tracking-wide uppercase">
                        {{ __('messages.contact_email') }}
                    </label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                           class="w-full bg-dark border border-dark-border text-white px-4 py-3 focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold transition-colors placeholder-gray-600"
                           placeholder="{{ __('messages.contact_email') }}">
                    @error('email')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Message --}}
                <div>
                    <label for="message" class="block text-sm font-medium text-gray-400 mb-2 tracking-wide uppercase">
                        {{ __('messages.contact_message') }}
                    </label>
                    <textarea name="message" id="message" rows="6" required
                              class="w-full bg-dark border border-dark-border text-white px-4 py-3 focus:border-gold focus:outline-none focus:ring-1 focus:ring-gold transition-colors resize-none placeholder-gray-600"
                              placeholder="{{ __('messages.contact_message') }}">{{ old('message') }}</textarea>
                    @error('message')
                        <p class="mt-1 text-sm text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <div>
                    <button type="submit"
                            :disabled="sending"
                            :class="sending ? 'opacity-60 cursor-not-allowed' : 'hover:bg-gold-light'"
                            class="w-full py-4 bg-gold text-dark font-bold text-lg transition-colors tracking-wide uppercase flex items-center justify-center gap-3">
                        <span x-show="sending" class="inline-block w-5 h-5 border-2 border-dark border-t-transparent rounded-full animate-spin"></span>
                        <span x-text="sending ? '{{ __('messages.contact_sending') }}' : '{{ __('messages.contact_send') }}'"></span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Direct contact info (admin-managed; shown only when set) --}}
        @php
            try {
                $contactEmail = \App\Models\Setting::get('contact_email');
                $contactPhone = \App\Models\Setting::get('contact_phone');
            } catch (\Throwable) {
                $contactEmail = $contactPhone = null;
            }
        @endphp
        @if($contactEmail || $contactPhone)
        <div class="mt-10 text-center" data-aos="fade-up">
            <div class="text-gray-500 text-sm tracking-widest uppercase mb-4">{{ __('messages.contact_direct') }}</div>
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                @if($contactEmail)
                    <a href="mailto:{{ $contactEmail }}"
                       class="inline-flex items-center gap-2 px-6 py-3 border border-dark-border text-gray-300 hover:border-gold hover:text-gold transition-colors">
                        <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        {{ $contactEmail }}
                    </a>
                @endif
                @if($contactPhone)
                    <a href="tel:{{ preg_replace('/[^+0-9]/', '', $contactPhone) }}"
                       class="inline-flex items-center gap-2 px-6 py-3 border border-dark-border text-gray-300 hover:border-gold hover:text-gold transition-colors">
                        <svg class="w-5 h-5 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                        </svg>
                        {{ $contactPhone }}
                    </a>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
