@extends('layouts.app')

@section('title', '429 - Padelio Turnyrai')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-dark px-4">
    <div class="text-center max-w-lg">
        <div class="text-gold text-sm font-semibold tracking-[0.3em] uppercase mb-6">429</div>
        <h1 class="text-5xl md:text-7xl font-black text-white mb-6 leading-none">
            {{ __('messages.error_429_title') }}
        </h1>
        <p class="text-gray-400 text-lg mb-10">{{ __('messages.error_429_text') }}</p>
        <a href="{{ url('/') }}"
           class="inline-block px-8 py-4 bg-gold text-dark font-bold hover:bg-gold-light transition-colors text-lg">
            {{ __('messages.error_back_home') }}
        </a>
    </div>
</div>
@endsection
