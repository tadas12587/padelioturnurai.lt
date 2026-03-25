<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Padelio Turnyrai')</title>

    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    {{-- Tailwind Play CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    dark: '#0A0A0F',
                    'dark-card': '#111118',
                    'dark-border': '#1E1E2E',
                    gold: '#C9A84C',
                    'gold-light': '#E5C76B',
                },
                fontFamily: {
                    sans: ['Inter', 'system-ui', 'sans-serif'],
                }
            }
        }
    }
    </script>

    {{-- AOS CSS --}}
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    {{-- GLightbox CSS --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css">

    {{-- Custom styles --}}
    <style>
        :root {
            --dark: #0A0A0F;
            --dark-card: #111118;
            --gold: #C9A84C;
            --gold-light: #E5C76B;
            --text: #F5F5F0;
            --muted: #9CA3AF;
        }

        body {
            background-color: var(--dark);
            color: var(--text);
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--dark);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--gold);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--gold-light);
        }

        /* Smooth scroll */
        html {
            scroll-behavior: smooth;
        }

        /* Selection color */
        ::selection {
            background-color: var(--gold);
            color: var(--dark);
        }
    </style>

    @stack('styles')
</head>
<body class="font-sans antialiased bg-dark text-white min-h-screen">

    {{-- Navigation --}}
    <nav id="main-nav" class="fixed top-0 left-0 right-0 z-50 transition-all duration-300" x-data="{ mobileOpen: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                {{-- Logo --}}
                <a href="{{ lroute('home') }}" class="text-gold font-black text-xl tracking-widest">
                    PADELIO TURNYRAI
                </a>

                {{-- Desktop Nav Links --}}
                <div class="hidden md:flex items-center gap-8">
                    <a href="{{ lroute('home') }}" class="text-gray-300 hover:text-gold transition-colors text-sm font-medium tracking-wide uppercase">
                        {{ __('messages.nav_home') }}
                    </a>
                    <a href="{{ lroute('tournaments') }}" class="text-gray-300 hover:text-gold transition-colors text-sm font-medium tracking-wide uppercase">
                        {{ __('messages.nav_tournaments') }}
                    </a>
                    <a href="{{ lroute('news.index') }}" class="text-gray-300 hover:text-gold transition-colors text-sm font-medium tracking-wide uppercase">
                        {{ __('messages.nav_news') }}
                    </a>
                    <a href="{{ lroute('contact') }}" class="text-gray-300 hover:text-gold transition-colors text-sm font-medium tracking-wide uppercase">
                        {{ __('messages.nav_contact') }}
                    </a>
                    <a href="{{ lroute('proposal') }}" class="px-4 py-2 border border-gold text-gold hover:bg-gold hover:text-dark transition-colors text-xs font-black tracking-widest uppercase">
                        {{ __('messages.nav_proposal') }}
                    </a>

                    {{-- Language Switcher --}}
                    @php
                        $currentLocale = app()->getLocale();
                        $currentPath = request()->path();
                        // Strip locale prefix if present
                        $pathWithoutLocale = preg_replace('#^(lt|en)/?#', '', $currentPath);
                        $pathWithoutLocale = $pathWithoutLocale ?: '/';
                        $ltUrl = url('/' . ltrim($pathWithoutLocale, '/'));
                        $enUrl = url('/en/' . ltrim($pathWithoutLocale, '/'));
                    @endphp
                    <div class="flex items-center gap-1 ml-4 border-l border-dark-border pl-4">
                        <a href="{{ $ltUrl }}"
                           class="px-2 py-1 text-xs font-bold tracking-wider {{ $currentLocale === 'lt' ? 'text-gold' : 'text-gray-500 hover:text-white' }} transition-colors">
                            LT
                        </a>
                        <span class="text-dark-border">|</span>
                        <a href="{{ $enUrl }}"
                           class="px-2 py-1 text-xs font-bold tracking-wider {{ $currentLocale === 'en' ? 'text-gold' : 'text-gray-500 hover:text-white' }} transition-colors">
                            EN
                        </a>
                    </div>
                </div>

                {{-- Mobile Hamburger --}}
                <button @click="mobileOpen = !mobileOpen" class="md:hidden text-gray-300 hover:text-gold transition-colors">
                    <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg x-show="mobileOpen" x-cloak class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Mobile Menu --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="md:hidden bg-dark-card/95 backdrop-blur-md border-t border-dark-border">
            <div class="px-4 py-6 space-y-4">
                <a href="{{ lroute('home') }}" class="block text-gray-300 hover:text-gold transition-colors text-sm font-medium tracking-wide uppercase">
                    {{ __('messages.nav_home') }}
                </a>
                <a href="{{ lroute('tournaments') }}" class="block text-gray-300 hover:text-gold transition-colors text-sm font-medium tracking-wide uppercase">
                    {{ __('messages.nav_tournaments') }}
                </a>
                <a href="{{ lroute('news.index') }}" class="block text-gray-300 hover:text-gold transition-colors text-sm font-medium tracking-wide uppercase">
                    {{ __('messages.nav_news') }}
                </a>
                <a href="{{ lroute('contact') }}" class="block text-gray-300 hover:text-gold transition-colors text-sm font-medium tracking-wide uppercase">
                    {{ __('messages.nav_contact') }}
                </a>
                <a href="{{ lroute('proposal') }}" class="block text-gold font-black text-sm tracking-widest uppercase">
                    {{ __('messages.nav_proposal') }}
                </a>
                <div class="flex items-center gap-2 pt-4 border-t border-dark-border">
                    <a href="{{ $ltUrl }}" class="px-3 py-1 text-xs font-bold {{ $currentLocale === 'lt' ? 'text-gold' : 'text-gray-500' }}">LT</a>
                    <span class="text-dark-border">|</span>
                    <a href="{{ $enUrl }}" class="px-3 py-1 text-xs font-bold {{ $currentLocale === 'en' ? 'text-gold' : 'text-gray-500' }}">EN</a>
                </div>
            </div>
        </div>
    </nav>

    {{-- Main Content --}}
    <main>
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="bg-dark-card border-t border-dark-border py-16">
        <div class="max-w-6xl mx-auto px-4">
            <div class="flex flex-col md:flex-row items-center justify-between gap-8">
                {{-- Logo --}}
                <div class="text-center md:text-left">
                    <a href="{{ lroute('home') }}" class="text-gold font-black text-lg tracking-widest">PADELIO TURNYRAI</a>
                    <p class="text-gray-600 text-sm mt-2">&copy; {{ date('Y') }} Padelioturnyrai.lt. {{ __('messages.footer_rights') }}.</p>
                </div>

                {{-- Social Links --}}
                <div class="flex items-center gap-6">
                    <a href="#" class="text-gray-500 hover:text-gold transition-colors" aria-label="Facebook">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                        </svg>
                    </a>
                    <a href="#" class="text-gray-500 hover:text-gold transition-colors" aria-label="Instagram">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    {{-- Scripts --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <script>
        // AOS init
        AOS.init({ duration: 800, once: true, offset: 50 });

        // GSAP
        gsap.registerPlugin(ScrollTrigger);

        // Nav scroll effect
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('main-nav');
            if (window.scrollY > 50) {
                nav.classList.add('bg-dark/95', 'backdrop-blur-md', 'shadow-lg');
            } else {
                nav.classList.remove('bg-dark/95', 'backdrop-blur-md', 'shadow-lg');
            }
        });

        // Hero animation
        gsap.from('#hero-content > *', {
            opacity: 0, y: 30, duration: 1, stagger: 0.15, ease: 'power3.out', delay: 0.3
        });

        // CountUp for stats
        function countUp(el) {
            const target = parseInt(el.dataset.target);
            const duration = 2000;
            const step = target / (duration / 16);
            let current = 0;
            const timer = setInterval(() => {
                current += step;
                if (current >= target) { current = target; clearInterval(timer); }
                el.textContent = Math.floor(current).toLocaleString();
            }, 16);
        }

        // Trigger countup when stats section visible
        const statsObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.querySelectorAll('[data-target]').forEach(countUp);
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.3 });

        const statsSection = document.getElementById('stats-section');
        if (statsSection) statsObserver.observe(statsSection);
    </script>

    @stack('scripts')
</body>
</html>
