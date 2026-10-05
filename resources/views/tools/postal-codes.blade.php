@extends('layouts.app')

@section('title', ($query !== '' ? 'PLZ ' . $query . ' — ' : '') . 'PLZ-Suche Deutschland | ' . \App\Support\SiteSettings::name())
@section('meta_description', 'Postleitzahl suchen: Geben Sie eine PLZ ein und finden Sie Ort, Kreis und Bundesland — oder suchen Sie umgekehrt alle Postleitzahlen einer Stadt.')

@section('content')
    <nav aria-label="Breadcrumb" class="mb-4 text-xs text-gray-500">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="{{ route('home') }}" class="hover:text-[var(--brand)]">Startseite</a></li>
            <li aria-hidden="true" class="text-gray-300">›</li>
            <li class="text-gray-700" aria-current="page">PLZ-Suche</li>
        </ol>
    </nav>

    <header class="mb-6 border-b-2 border-[var(--brand)] pb-3">
        <h1 class="text-2xl font-black uppercase">PLZ-Suche</h1>
    </header>

    <div class="max-w-3xl">
        <p class="text-sm text-gray-600">
            Postleitzahl eingeben und Ort, Kreis und Bundesland sehen — oder einen
            Ortsnamen eingeben und alle Postleitzahlen dazu finden.
        </p>

        <form action="{{ route('tools.plz') }}" method="GET"
              class="mt-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <label for="q" class="block text-sm font-semibold text-gray-700">Postleitzahl oder Ort</label>

            <div class="mt-2 flex flex-wrap gap-2">
                <input type="text" id="q" name="q" value="{{ $query }}" maxlength="60"
                       placeholder="z. B. 10115 oder Berlin" autocomplete="postal-code"
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none sm:w-72">
                <button class="rounded-md bg-[var(--brand)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Suchen</button>
            </div>
        </form>

        @if ($failed)
            <div class="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-700">
                Die Postleitzahl-Datenbank ist gerade nicht erreichbar. Bitte
                versuchen Sie es in ein paar Minuten noch einmal.
            </div>
        @elseif ($results !== null && count($results) === 0)
            <div class="mt-6 rounded-md bg-gray-50 p-4 text-sm text-gray-600">
                Zu <strong>{{ $query }}</strong> wurde nichts gefunden. Prüfen Sie die
                Schreibweise, oder geben Sie eine fünfstellige Postleitzahl ein.
            </div>
        @elseif ($results !== null)
            <h2 class="mt-8 text-base font-bold text-gray-900">
                {{ count($results) }} {{ count($results) === 1 ? 'Treffer' : 'Treffer' }} für „{{ $query }}"
            </h2>

            <div class="mt-3 overflow-x-auto rounded-lg bg-white shadow-sm ring-1 ring-gray-100">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th scope="col" class="px-4 py-3">PLZ</th>
                            <th scope="col" class="px-4 py-3">Ort</th>
                            <th scope="col" class="px-4 py-3">Kreis</th>
                            <th scope="col" class="px-4 py-3">Bundesland</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($results as $row)
                            <tr class="border-b border-gray-100 last:border-0">
                                <td class="px-4 py-3 font-bold text-[var(--brand)]">{{ $row['postal_code'] }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ $row['place'] }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $row['district'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $row['state'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Berlin first: this is a Berlin paper, and these are the codes its
             readers look up. --}}
        <div class="mt-8">
            <h2 class="text-base font-bold text-gray-900">Häufig gesucht in Berlin</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['10115' => 'Mitte', '10247' => 'Friedrichshain', '10435' => 'Prenzlauer Berg', '10707' => 'Charlottenburg', '12043' => 'Neukölln', '12489' => 'Adlershof', '13353' => 'Wedding', '14169' => 'Zehlendorf'] as $code => $name)
                    <a href="{{ route('tools.plz', ['q' => $code]) }}"
                       class="rounded-full border border-gray-300 px-3.5 py-1.5 text-xs font-semibold text-gray-700 transition hover:border-[var(--brand)] hover:bg-[var(--brand)] hover:text-white">
                        {{ $code }} {{ $name }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="mt-8 text-sm leading-relaxed text-gray-600">
            <h2 class="text-base font-bold text-gray-900">Wie sind Postleitzahlen aufgebaut?</h2>
            <p class="mt-2">
                Deutsche Postleitzahlen haben seit 1993 fünf Ziffern. Die erste
                Ziffer steht für eine der zehn Leitzonen, die ersten beiden für die
                Leitregion. Berlin belegt den Bereich von 10115 bis 14199 — eine
                einzelne Stadt mit mehreren hundert Postleitzahlen.
            </p>
            <p class="mt-2">
                Die Daten stammen aus dem amtlichen Gemeindeverzeichnis des
                Statistischen Bundesamtes.
            </p>
        </div>
    </div>
@endsection
