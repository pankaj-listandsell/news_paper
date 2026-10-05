@extends('layouts.app')

@section('title', 'Goldpreis heute in Euro — Gold und Silber aktuell | ' . \App\Support\SiteSettings::name())
@section('meta_description', 'Aktueller Goldpreis in Euro je Gramm, Unze und Kilogramm. Dazu der Silberpreis und der Euro-Referenzkurs der EZB — mehrmals pro Stunde aktualisiert.')

@section('content')
    <nav aria-label="Breadcrumb" class="mb-4 text-xs text-gray-500">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="{{ route('home') }}" class="hover:text-[var(--brand)]">Startseite</a></li>
            <li aria-hidden="true" class="text-gray-300">›</li>
            <li class="text-gray-700" aria-current="page">Goldpreis</li>
        </ol>
    </nav>

    <header class="mb-6 border-b-2 border-[var(--brand)] pb-3">
        <h1 class="text-2xl font-black uppercase">Goldpreis heute</h1>
    </header>

    <div class="max-w-3xl">
        @if (! $prices)
            <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">
                Die Kurse sind gerade nicht abrufbar. Bitte versuchen Sie es in ein
                paar Minuten noch einmal.
            </div>
        @else
            <p class="text-sm text-gray-600">
                Feingold (999) in Euro, umgerechnet zum Euro-Referenzkurs der
                Europäischen Zentralbank. Stand:
                <strong>{{ $prices['fetched_at']->timezone('Europe/Berlin')->format('d.m.Y, H:i') }} Uhr</strong>.
            </p>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                @foreach ([
                    'Gramm'     => $prices['gold']['gram'],
                    'Unze'      => $prices['gold']['ounce'],
                    'Kilogramm' => $prices['gold']['kilo'],
                ] as $unit => $value)
                    <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
                        <p class="text-xs uppercase tracking-wider text-gray-500">Gold je {{ $unit }}</p>
                        <p class="mt-1 text-2xl font-black text-[var(--brand)]">
                            {{ number_format($value, 2, ',', '.') }}&nbsp;€
                        </p>
                    </div>
                @endforeach
            </div>

            @if ($prices['silver'])
                <h2 class="mt-8 text-base font-bold text-gray-900">Silberpreis</h2>
                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        'Gramm'     => $prices['silver']['gram'],
                        'Unze'      => $prices['silver']['ounce'],
                        'Kilogramm' => $prices['silver']['kilo'],
                    ] as $unit => $value)
                        <div class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100">
                            <p class="text-xs uppercase tracking-wider text-gray-500">Silber je {{ $unit }}</p>
                            <p class="mt-1 text-lg font-bold text-gray-900">
                                {{ number_format($value, 2, ',', '.') }}&nbsp;€
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-6 text-xs text-gray-500">
                Umgerechnet mit 1&nbsp;€ = {{ number_format($prices['usd_per_eur'], 4, ',', '.') }}&nbsp;US-Dollar
                ({{ $prices['rate_source'] }}).
            </p>
        @endif

        <div class="mt-8 text-sm leading-relaxed text-gray-600">
            <h2 class="text-base font-bold text-gray-900">Warum schwankt der Goldpreis?</h2>
            <p class="mt-2">
                Gold wird international in US-Dollar je Feinunze gehandelt. Für
                Käufer in Deutschland hängt der Preis deshalb an zwei Größen: am
                Weltmarktpreis und am Wechselkurs. Ein fallender Euro verteuert Gold
                hierzulande selbst dann, wenn der Dollarpreis unverändert bleibt.
            </p>
            <p class="mt-2">
                Eine Feinunze entspricht 31,1035&nbsp;Gramm. Die hier genannten Werte
                sind Weltmarktpreise für Feingold; beim Kauf von Barren oder Münzen
                kommen Aufschlag und Händlerspanne hinzu.
            </p>
            <p class="mt-3 text-xs text-gray-500">
                Angaben ohne Gewähr. Keine Anlageberatung.
            </p>
        </div>
    </div>
@endsection
