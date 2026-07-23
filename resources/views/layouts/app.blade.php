<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $site['site_name'])</title>
    <meta name="description" content="@yield('meta_description', $site['site_description'])">
    @if (($site['search_indexing'] ?? '1') === '0')
        <meta name="robots" content="noindex, nofollow">
    @endif
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

    {{-- Sitewide structured data: publisher + site search box --}}
    <script type="application/ld+json">
    @php
        $orgLd = [
            '@context' => 'https://schema.org',
            '@graph'   => [
                [
                    '@type' => 'NewsMediaOrganization',
                    '@id'   => url('/') . '#organization',
                    'name'  => $site['site_name'],
                    'url'   => url('/'),
                    'logo'  => $siteLogo ?? asset('build/assets/og-default.png'),
                    'sameAs' => array_values($siteSocial),
                ],
                [
                    '@type'     => 'WebSite',
                    '@id'       => url('/') . '#website',
                    'name'      => $site['site_name'],
                    'url'       => url('/'),
                    'publisher' => ['@id' => url('/') . '#organization'],
                    'inLanguage' => 'de-DE',
                    'potentialAction' => [
                        '@type'  => 'SearchAction',
                        'target' => [
                            '@type'       => 'EntryPoint',
                            'urlTemplate' => route('search') . '?q={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
            ],
        ];
    @endphp
    {!! json_encode($orgLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    <style>
        :root {
            --brand: {{ $brand['base'] }};
            --brand-dark: {{ $brand['dark'] }};
            --brand-soft: {{ $brand['soft'] }};
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Google Analytics — when the cookie banner is ON, it loads only AFTER
         the visitor consents (see the bottom of the body). When the banner is
         OFF it loads directly, as before. --}}
    @php
        $gtmId = $site['gtm_id'] ?? '';
        $cookieBanner = ($site['cookie_banner'] ?? '1') !== '0';
    @endphp
    @if ($gtmId && ! $cookieBanner)
        {{-- Google Tag Manager --}}
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer',@json($gtmId));</script>
    @endif
</head>
<body class="bg-gray-50 text-gray-900 antialiased">

    @if ($gtmId && ! $cookieBanner)
        {{-- Google Tag Manager (noscript). Only rendered when consent is not
             required — with JS off there is no way to ask for consent first. --}}
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ urlencode($gtmId) }}"
                          height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    {{-- Masthead --}}
    <header class="border-b bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 md:py-6">
            <a href="{{ route('home') }}" class="flex items-center gap-2">
                @if ($siteLogo)
                    <img src="{{ $siteLogo }}" alt="{{ $site['site_name'] }}" class="h-10 w-auto md:h-12">
                @else
                    <span class="text-2xl font-black tracking-tight text-[var(--brand)] md:text-3xl">{{ $site['site_name'] }}</span>
                @endif
            </a>
            <form action="{{ route('search') }}" method="GET" class="hidden items-center md:flex">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nachrichten suchen..."
                       class="w-64 rounded-l-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">
                <button class="rounded-r-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Los</button>
            </form>
        </div>

        {{-- Mobile search --}}
        <form action="{{ route('search') }}" method="GET" class="flex border-t px-4 py-2 md:hidden">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nachrichten suchen..."
                   class="w-full rounded-l-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">
            <button class="rounded-r-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Los</button>
        </form>
    </header>

    {{-- Category nav — scrolls sideways on mobile, sticks to the top on scroll --}}
    <nav class="sticky top-0 z-30 border-b bg-white shadow-sm">
        @php $currentCategory = request()->route('category'); @endphp
        <div class="no-scrollbar mx-auto flex max-w-7xl items-center gap-x-6 overflow-x-auto whitespace-nowrap px-4 text-sm font-semibold">
            <a href="{{ route('home') }}"
               @class([
                   'shrink-0 border-b-2 py-3',
                   'border-[var(--brand)] text-[var(--brand)]' => request()->routeIs('home'),
                   'border-transparent text-gray-700 hover:text-[var(--brand)]' => ! request()->routeIs('home'),
               ])>Startseite</a>
            @foreach ($navCategories as $cat)
                @php $isActive = request()->routeIs('category.show') && $currentCategory?->id === $cat->id; @endphp
                <a href="{{ route('category.show', $cat) }}"
                   @class([
                       'shrink-0 border-b-2 py-3',
                       'border-[var(--brand)] text-[var(--brand)]' => $isActive,
                       'border-transparent text-gray-700 hover:text-[var(--brand)]' => ! $isActive,
                   ])>{{ $cat->name }}</a>
            @endforeach
        </div>
    </nav>

    {{-- Breaking news ticker --}}
    @if ($breakingNews->isNotEmpty())
        <div class="bg-[var(--brand)] text-white">
            <div class="mx-auto flex max-w-7xl items-center gap-3 overflow-hidden px-4 py-2">
                <span class="shrink-0 rounded bg-white px-2 py-0.5 text-xs font-bold uppercase text-[var(--brand)]">Eilmeldung</span>
                <div class="flex gap-8 whitespace-nowrap text-sm animate-[marquee_25s_linear_infinite]">
                    @foreach ($breakingNews as $b)
                        <a href="{{ route('article.show', $b) }}" class="hover:underline">{{ $b->title }}</a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

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
                           class="w-full rounded-l-md border-0 bg-white px-3 py-2 text-sm text-gray-900 placeholder-gray-500 focus:outline-none">
                    <button class="shrink-0 rounded-r-md bg-[var(--brand)] px-4 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Anmelden</button>
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
                @if ($cookieBanner)
                    <button type="button" onclick="openCookieSettings()" class="hover:text-white">Cookie-Einstellungen</button>
                @endif
            </div>
            &copy; {{ now()->year }} {{ $site['site_name'] }}. {{ $site['copyright_text'] }}
        </div>
    </footer>

    <style>
        @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    {{-- GDPR cookie consent — a centered modal; Analytics loads only on consent --}}
    @if ($cookieBanner)
        <div id="cookie-consent" class="fixed inset-0 z-50 hidden" role="dialog" aria-modal="true" aria-labelledby="cc-title">
            <div class="absolute inset-0 bg-black/60"></div>
            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-2xl sm:p-8">
                    <button id="cc-close" type="button" aria-label="Schließen"
                            class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full text-2xl leading-none text-gray-400 hover:bg-gray-100 hover:text-gray-700">&times;</button>

                    <h2 id="cc-title" class="pr-6 text-xl font-black text-gray-900">Diese Website verwendet Cookies</h2>
                    <p class="mt-4 text-sm leading-relaxed text-gray-600">
                        Durch die Auswahl von „Alle Cookies akzeptieren“ stimmen Sie der Verwendung von Cookies zu,
                        um Ihnen eine bessere Benutzererfahrung zu bieten und die Website-Nutzung zu analysieren.
                        Durch Klick auf „Einstellungen anpassen“ können Sie auswählen, welche Cookies erlaubt sind.
                        Nur die notwendigen Cookies sind für das einwandfreie Funktionieren unserer Website
                        erforderlich und können nicht abgelehnt werden.
                    </p>

                    {{-- Category toggles — revealed by "Einstellungen anpassen" --}}
                    <div id="cc-settings" class="mt-5 hidden space-y-3">
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">
                            <span class="text-sm">
                                <span class="font-semibold text-gray-900">Notwendige Cookies</span>
                                <span class="block text-xs text-gray-500">Immer aktiv – für den Betrieb der Website erforderlich.</span>
                            </span>
                            <input type="checkbox" checked disabled class="h-5 w-5 shrink-0 accent-[var(--brand)]">
                        </label>
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">
                            <span class="text-sm">
                                <span class="font-semibold text-gray-900">Funktionale Cookies</span>
                                <span class="block text-xs text-gray-500">Immer aktiv – ermöglichen erweiterte Funktionen wie Videos und eingebettete Inhalte.</span>
                            </span>
                            <input id="cc-functional" type="checkbox" checked disabled class="h-5 w-5 shrink-0 accent-[var(--brand)]">
                        </label>
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">
                            <span class="text-sm">
                                <span class="font-semibold text-gray-900">Analyse-Cookies</span>
                                <span class="block text-xs text-gray-500">Google Tag Manager / Analytics – hilft uns, die Nutzung der Website zu verstehen.</span>
                            </span>
                            <input id="cc-analytics" type="checkbox" class="h-5 w-5 shrink-0 accent-[var(--brand)]">
                        </label>
                        <label class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">
                            <span class="text-sm">
                                <span class="font-semibold text-gray-900">Marketing-Cookies</span>
                                <span class="block text-xs text-gray-500">Werden verwendet, um Ihnen relevante Werbung anzuzeigen.</span>
                            </span>
                            <input id="cc-marketing" type="checkbox" class="h-5 w-5 shrink-0 accent-[var(--brand)]">
                        </label>
                    </div>

                    <div class="mt-6 flex flex-col gap-3">
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button id="cc-accept-all" type="button"
                                    class="flex-1 rounded-md bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Alle Cookies akzeptieren</button>
                            <button id="cc-necessary" type="button"
                                    class="flex-1 rounded-md bg-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Nur notwendige Cookies akzeptieren</button>
                        </div>
                        <button id="cc-customize" type="button"
                                class="rounded-md border border-[var(--brand)] px-4 py-2.5 text-sm font-semibold text-[var(--brand)] hover:bg-[var(--brand-soft)]">Einstellungen anpassen</button>
                        <button id="cc-save" type="button"
                                class="hidden rounded-md bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">Meine Auswahl speichern</button>
                    </div>

                    <p class="mt-4 text-center text-xs text-gray-400">
                        <a href="{{ route('page', 'datenschutz') }}" class="underline hover:text-gray-600">Datenschutz</a>
                        &middot;
                        <a href="{{ route('page', 'impressum') }}" class="underline hover:text-gray-600">Impressum</a>
                    </p>
                </div>
            </div>
        </div>
        <script>
            (function () {
                var KEY = 'cookie_consent';
                var modal = document.getElementById('cookie-consent');
                if (!modal) return;
                var GTM_ID = @json($gtmId ?: '');

                function loadGTM() {
                    if (!GTM_ID || window.__gtmLoaded) return;
                    window.__gtmLoaded = true;
                    window.dataLayer = window.dataLayer || [];
                    window.dataLayer.push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' });
                    var f = document.getElementsByTagName('script')[0];
                    var j = document.createElement('script');
                    j.async = true;
                    j.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(GTM_ID);
                    f.parentNode.insertBefore(j, f);
                }

                function open()  { modal.classList.remove('hidden'); document.documentElement.style.overflow = 'hidden'; }
                function close() { modal.classList.add('hidden');    document.documentElement.style.overflow = ''; }

                function readConsent() {
                    try {
                        var raw = localStorage.getItem(KEY);
                        if (!raw) return null;
                        if (raw === 'accepted') return { functional: true, analytics: true, marketing: true };
                        if (raw === 'declined') return { functional: false, analytics: false, marketing: false };
                        return JSON.parse(raw);
                    } catch (e) { return null; }
                }

                function save(consent) {
                    try { localStorage.setItem(KEY, JSON.stringify(consent)); } catch (e) {}
                    close();
                    if (consent.analytics) loadGTM();
                }

                var consent = readConsent();
                if (consent) {
                    if (consent.analytics) loadGTM();
                } else {
                    open();
                }

                document.getElementById('cc-accept-all').addEventListener('click', function () {
                    save({ functional: true, analytics: true, marketing: true });
                });
                document.getElementById('cc-necessary').addEventListener('click', function () {
                    // Functional is always on (locked); only Analytics/Marketing are declined.
                    save({ functional: true, analytics: false, marketing: false });
                });
                document.getElementById('cc-close').addEventListener('click', function () {
                    // Closing without a choice grants no optional consent (GDPR-safe).
                    save({ functional: true, analytics: false, marketing: false });
                });
                document.getElementById('cc-customize').addEventListener('click', function () {
                    document.getElementById('cc-settings').classList.remove('hidden');
                    document.getElementById('cc-save').classList.remove('hidden');
                    this.classList.add('hidden');
                });
                document.getElementById('cc-save').addEventListener('click', function () {
                    save({
                        functional: true, // locked on
                        analytics:  document.getElementById('cc-analytics').checked,
                        marketing:  document.getElementById('cc-marketing').checked,
                    });
                });

                window.openCookieSettings = function () {
                    var c = readConsent() || {};
                    document.getElementById('cc-functional').checked = true; // always on
                    document.getElementById('cc-analytics').checked  = !!c.analytics;
                    document.getElementById('cc-marketing').checked  = !!c.marketing;
                    open();
                };
            })();
        </script>
    @endif

    @stack('scripts')
</body>
</html>
