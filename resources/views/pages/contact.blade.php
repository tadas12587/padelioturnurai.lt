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
    </div>
</div>
@endsection
