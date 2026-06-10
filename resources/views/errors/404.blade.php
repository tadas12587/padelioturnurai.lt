@extends('layouts.app')

@section('title', '404 - Padelio Turnyrai')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-dark px-4">
    <div class="text-center max-w-lg">
        <div class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-6">404</div>
        <h1 class="text-6xl md:text-8xl font-black text-white mb-6 leading-none">
            {{ __('messages.error_404_title') }}
        </h1>
        <p class="text-gray-400 text-lg mb-10">{{ __('messages.error_404_text') }}</p>
        <a href="{{ url('/') }}"
           class="inline-block px-8 py-4 bg-gold text-dark font-bold hover:bg-gold-light transition-colors text-lg">
            {{ __('messages.error_back_home') }}
        </a>
    </div>
</div>
@endsection
