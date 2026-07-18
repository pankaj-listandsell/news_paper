<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $site['site_name'])</title>
    <meta name="description" content="@yield('meta_description', $site['site_description'])">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="alternate" type="application/rss+xml" title="{{ $site['site_name'] }} RSS" href="{{ route('rss') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">

    {{-- Open Graph / Twitter — link previews when the page is shared --}}
    <meta property="og:site_name" content="{{ $site['site_name'] }}">
    <meta property="og:locale" content="de_DE">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="@yield('og_title', $site['site_name'])">
    <meta property="og:description" content="@yield('meta_description', $site['site_description'])">
    <meta property="og:image" content="@yield('og_image', $siteLogo ?? asset('build/assets/og-default.png'))">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', $site['site_name'])">
    <meta name="twitter:description" content="@yield('meta_description', $site['site_description'])">
    <meta name="twitter:image" content="@yield('og_image', $siteLogo ?? asset('build/assets/og-default.png'))">
    @stack('meta')

    @if ($siteFavicon)
        <link rel="icon" href="{{ $siteFavicon }}">
        <link rel="apple-touch-icon" href="{{ $siteFavicon }}">
    @endif

    @if ($site['google_site_verification'])
        <meta name="google-site-verification" content="{{ $site['google_site_verification'] }}">
    @endif

    <style>
        :root {
            --brand: {{ $brand['base'] }};
            --brand-dark: {{ $brand['dark'] }};
            --brand-soft: {{ $brand['soft'] }};
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Google Analytics (public site only) --}}
    @if ($site['google_analytics_id'])
        @php $gaId = $site['google_analytics_id']; @endphp
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($gaId) }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($gaId));
        </script>
    @endif
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

    {{-- Top bar --}}
    <div class="bg-gray-900 text-gray-300 text-xs">
        <div class="mx-auto max-w-7xl px-4 py-2 flex items-center justify-between">
            <span>{{ now()->locale('de')->translatedFormat('l, d. F Y') }}</span>
            <nav class="flex gap-4">
                <a href="{{ route('home') }}" class="hover:text-white">Startseite</a>
                <a href="/admin" class="hover:text-white">Admin</a>
            </nav>
        </div>
    </div>

    {{-- Masthead --}}
    <header class="bg-white border-b">
        <div class="mx-auto max-w-7xl px-4 py-6 flex items-center justify-between gap-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                @if ($siteLogo)
                    <img src="{{ $siteLogo }}" alt="{{ $site['site_name'] }}" class="h-12 w-auto">
                @else
                    <span class="text-3xl font-black tracking-tight text-[var(--brand)]">{{ $site['site_name'] }}</span>
                @endif
            </a>
            <form action="{{ route('search') }}" method="GET" class="hidden md:flex items-center">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nachrichten suchen..."
                       class="w-64 rounded-l-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">
                <button class="rounded-r-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Los</button>
            </form>
        </div>

        {{-- Category nav --}}
        <nav class="border-t bg-white">
            <div class="mx-auto max-w-7xl px-4 flex flex-wrap items-center gap-x-6 gap-y-1 py-3 text-sm font-semibold">
                <a href="{{ route('home') }}" class="text-[var(--brand)] hover:text-[var(--brand-dark)]">Startseite</a>
                @foreach ($navCategories as $cat)
                    <a href="{{ route('category.show', $cat) }}" class="text-gray-700 hover:text-[var(--brand)]">{{ $cat->name }}</a>
                @endforeach
            </div>
        </nav>

        {{-- Breaking news ticker --}}
        @if ($breakingNews->isNotEmpty())
            <div class="bg-[var(--brand)] text-white">
                <div class="mx-auto max-w-7xl px-4 py-2 flex items-center gap-3 overflow-hidden">
                    <span class="shrink-0 rounded bg-white px-2 py-0.5 text-xs font-bold uppercase text-[var(--brand)]">Eilmeldung</span>
                    <div class="flex gap-8 whitespace-nowrap text-sm animate-[marquee_25s_linear_infinite]">
                        @foreach ($breakingNews as $b)
                            <a href="{{ route('article.show', $b) }}" class="hover:underline">{{ $b->title }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8">
        @yield('content')
    </main>

    {{-- Footer --}}
    <footer class="mt-12 bg-gray-900 text-gray-300">
        <div class="mx-auto max-w-7xl px-4 py-10 grid gap-8 md:grid-cols-3">
            <div>
                <h3 class="text-xl font-black text-white">{{ $site['site_name'] }}</h3>
                <p class="mt-2 text-sm text-gray-400">{{ $site['site_tagline'] }}</p>

                @if ($site['contact_email'])
                    <p class="mt-3 text-sm">
                        <a href="mailto:{{ $site['contact_email'] }}" class="text-gray-400 hover:text-white">{{ $site['contact_email'] }}</a>
                    </p>
                @endif

                @if (count($siteSocial))
                    <div class="mt-3 flex flex-wrap gap-3 text-sm">
                        @foreach ($siteSocial as $label => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener"
                               class="text-gray-400 hover:text-white">{{ $label }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
            <div>
                <h4 class="font-semibold text-white">Kategorien</h4>
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($navCategories->take(6) as $cat)
                        <li><a href="{{ route('category.show', $cat) }}" class="hover:text-white">{{ $cat->name }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h4 class="font-semibold text-white">Newsletter</h4>
                <p class="mt-2 text-sm text-gray-400">{{ $site['newsletter_text'] }}</p>
                @if (session('subscribe_status'))
                    <p class="mt-2 text-sm text-green-400">{{ session('subscribe_status') }}</p>
                @endif
                <form action="{{ route('subscribe') }}" method="POST" class="mt-3 flex">
                    @csrf
                    <input type="email" name="email" required placeholder="E-Mail-Adresse"
                           class="w-full rounded-l-md px-3 py-2 text-sm text-gray-900 focus:outline-none">
                    <button class="rounded-r-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Anmelden</button>
                </form>
                @error('email')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="border-t border-gray-800 py-4 text-center text-xs text-gray-500">
            <div class="mb-2 flex flex-wrap justify-center gap-x-4 gap-y-1">
                @if (strip_tags($site['about_content']) !== '')
                    <a href="{{ route('page', 'ueber-uns') }}" class="hover:text-white">Über uns</a>
                @endif
                @if (strip_tags($site['imprint_content']) !== '')
                    <a href="{{ route('page', 'impressum') }}" class="hover:text-white">Impressum</a>
                @endif
                @if (strip_tags($site['privacy_content']) !== '')
                    <a href="{{ route('page', 'datenschutz') }}" class="hover:text-white">Datenschutz</a>
                @endif
                <a href="{{ route('rss') }}" class="hover:text-white">RSS</a>
            </div>
            &copy; {{ now()->year }} {{ $site['site_name'] }}. {{ $site['copyright_text'] }}
        </div>
    </footer>

    <style>
        @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
    </style>

    @stack('scripts')
</body>
</html>
