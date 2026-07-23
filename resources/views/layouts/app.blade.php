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
    <footer class="mt-16 border-t-4 border-[var(--brand)] bg-gray-900 text-gray-300">
        <div class="mx-auto max-w-7xl px-4 py-12">
            <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">

                {{-- Brand --}}
                <div class="lg:col-span-2">
                    <a href="{{ route('home') }}" class="inline-block">
                        @if ($siteLogo)
                            <img src="{{ $siteLogo }}" alt="{{ $site['site_name'] }}"
                                 class="h-11 w-auto rounded bg-white p-1.5">
                        @else
                            <span class="text-2xl font-black tracking-tight text-white">{{ $site['site_name'] }}</span>
                        @endif
                    </a>

                    @if ($site['site_tagline'])
                        <p class="mt-5 max-w-md text-sm leading-relaxed text-gray-400">{{ $site['site_tagline'] }}</p>
                    @endif

                    @if ($site['contact_email'])
                        <a href="mailto:{{ $site['contact_email'] }}"
                           class="mt-4 inline-flex items-center gap-2 text-sm text-gray-400 transition hover:text-white">
                            <span aria-hidden="true">✉</span>{{ $site['contact_email'] }}
                        </a>
                    @endif

                    @if (count($siteSocial))
                        <div class="mt-6 flex flex-wrap gap-2">
                            @foreach ($siteSocial as $label => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener"
                                   class="rounded-full border border-gray-700 px-3.5 py-1.5 text-xs font-semibold text-gray-400 transition hover:border-[var(--brand)] hover:bg-[var(--brand)] hover:text-white">{{ $label }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Categories --}}
                <div>
                    <h4 class="text-sm font-bold uppercase tracking-wider text-white">Kategorien</h4>
                    <ul class="mt-5 space-y-2.5 text-sm">
                        @foreach ($navCategories->take(6) as $cat)
                            <li>
                                <a href="{{ route('category.show', $cat) }}"
                                   class="text-gray-400 transition hover:text-white">{{ $cat->name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Service & legal --}}
                <div>
                    <h4 class="text-sm font-bold uppercase tracking-wider text-white">Service</h4>
                    <ul class="mt-5 space-y-2.5 text-sm">
                        @if (strip_tags($site['about_content']) !== '')
                            <li><a href="{{ route('page', 'ueber-uns') }}" class="text-gray-400 transition hover:text-white">Über uns</a></li>
                        @endif
                        <li><a href="{{ route('contact') }}" class="text-gray-400 transition hover:text-white">Kontakt</a></li>
                        @if (strip_tags($site['imprint_content']) !== '')
                            <li><a href="{{ route('page', 'impressum') }}" class="text-gray-400 transition hover:text-white">Impressum</a></li>
                        @endif
                        @if (strip_tags($site['privacy_content']) !== '')
                            <li><a href="{{ route('page', 'datenschutz') }}" class="text-gray-400 transition hover:text-white">Datenschutz</a></li>
                        @endif
                        <li><a href="{{ route('rss') }}" class="text-gray-400 transition hover:text-white">RSS-Feed</a></li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="border-t border-gray-800">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-5 text-xs text-gray-500 sm:flex-row">
                <p>&copy; {{ now()->year }} {{ $site['site_name'] }}. {{ $site['copyright_text'] }}</p>
                @if ($cookieBanner)
                    <button type="button" onclick="openCookieSettings()"
                            class="transition hover:text-white">Cookie-Einstellungen</button>
                @endif
            </div>
        </div>
    </footer>

    <style>
        @keyframes marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>

    {{-- GDPR cookie consent — a centered modal; Analytics loads only on consent --}}
    @if ($cookieBanner)
        {{-- Bottom bar. No overlay and no scroll lock, so the whole site stays
             clickable and scrollable while the notice is showing. --}}
        <div id="cookie-consent" class="fixed inset-x-0 bottom-0 z-50 hidden" role="region" aria-label="Cookie-Hinweis">
            <div class="border-t border-gray-200 bg-white shadow-[0_-4px_24px_rgba(0,0,0,0.10)]">
                <div class="mx-auto max-h-[80vh] max-w-7xl overflow-y-auto px-4 py-4">

                    {{-- Compact notice --}}
                    <div id="cc-bar" class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <p class="text-sm leading-relaxed text-gray-600">
                            Wir verwenden Cookies, um Ihnen die bestmögliche Erfahrung auf unserer Website zu bieten.
                            Sie können mehr darüber erfahren, welche Cookies wir verwenden, oder sie in den
                            <button type="button" id="cc-customize"
                                    class="font-semibold text-[var(--brand)] underline hover:no-underline">Einstellungen</button>
                            ausschalten.
                        </p>
                        <div class="flex shrink-0 flex-wrap gap-3">
                            <button id="cc-necessary" type="button"
                                    class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">Nur notwendige</button>
                            <button id="cc-accept-all" type="button"
                                    class="rounded-md bg-[var(--brand)] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[var(--brand-dark)]">Alle Cookies akzeptieren</button>
                        </div>
                    </div>

                    {{-- Settings — opened by "Einstellungen" --}}
                    <div id="cc-settings" class="hidden">
                        <div class="flex items-start justify-between gap-4">
                            <h2 class="text-base font-black text-gray-900">Cookie-Einstellungen</h2>
                            <button id="cc-back" type="button" aria-label="Schließen"
                                    class="-mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-2xl leading-none text-gray-400 transition hover:bg-gray-100 hover:text-gray-700">&times;</button>
                        </div>

                        <div class="mt-4 grid gap-3 sm:grid-cols-2">
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

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button id="cc-save" type="button"
                                    class="rounded-md bg-[var(--brand)] px-5 py-2 text-sm font-semibold text-white transition hover:bg-[var(--brand-dark)]">Meine Auswahl speichern</button>
                            <a href="{{ route('page', 'datenschutz') }}" class="text-xs text-gray-400 underline hover:text-gray-600">Datenschutz</a>
                            <a href="{{ route('page', 'impressum') }}" class="text-xs text-gray-400 underline hover:text-gray-600">Impressum</a>
                        </div>
                    </div>
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

                var bar      = document.getElementById('cc-bar');
                var settings = document.getElementById('cc-settings');

                // Nothing is locked — the page stays scrollable and clickable.
                function open()  { modal.classList.remove('hidden'); }
                function close() { modal.classList.add('hidden'); }

                function showSettings(on) {
                    settings.classList.toggle('hidden', ! on);
                    bar.classList.toggle('hidden', on);
                }

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
                document.getElementById('cc-customize').addEventListener('click', function () {
                    showSettings(true);
                });
                document.getElementById('cc-back').addEventListener('click', function () {
                    showSettings(false);
                });
                document.getElementById('cc-save').addEventListener('click', function () {
                    save({
                        functional: true, // locked on
                        analytics:  document.getElementById('cc-analytics').checked,
                        marketing:  document.getElementById('cc-marketing').checked,
                    });
                });

                // Footer link — reopen straight on the settings view.
                window.openCookieSettings = function () {
                    var c = readConsent() || {};
                    document.getElementById('cc-functional').checked = true; // always on
                    document.getElementById('cc-analytics').checked  = !!c.analytics;
                    document.getElementById('cc-marketing').checked  = !!c.marketing;
                    showSettings(true);
                    open();
                };
            })();
        </script>
    @endif

    @stack('scripts')
</body>
</html>
