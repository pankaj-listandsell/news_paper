@extends('layouts.app')

@section('title', 'Altersrechner — Alter exakt berechnen | ' . \App\Support\SiteSettings::name())
@section('meta_description', 'Alter in Jahren, Monaten und Tagen berechnen. Geben Sie Ihr Geburtsdatum ein und erfahren Sie sofort, wie alt Sie genau sind und wann der nächste Geburtstag ist.')

@section('content')
    <nav aria-label="Breadcrumb" class="mb-4 text-xs text-gray-500">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="{{ route('home') }}" class="hover:text-[var(--brand)]">Startseite</a></li>
            <li aria-hidden="true" class="text-gray-300">›</li>
            <li class="text-gray-700" aria-current="page">Altersrechner</li>
        </ol>
    </nav>

    <header class="mb-6 border-b-2 border-[var(--brand)] pb-3">
        <h1 class="text-2xl font-black uppercase">Altersrechner</h1>
    </header>

    <div class="max-w-2xl">
        <p class="text-sm text-gray-600">
            Geben Sie Ihr Geburtsdatum ein. Das Alter wird direkt in Ihrem Browser
            berechnet — es wird nichts gespeichert und nichts an uns gesendet.
        </p>

        <div class="mt-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
            <label for="birthdate" class="block text-sm font-semibold text-gray-700">Geburtsdatum</label>
            <input type="date" id="birthdate"
                   max="{{ now()->timezone('Europe/Berlin')->format('Y-m-d') }}"
                   class="mt-2 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none sm:w-64">

            <p id="age-error" class="mt-3 hidden text-sm text-red-600"></p>

            <div id="age-result" class="mt-6 hidden">
                <div class="rounded-md bg-gray-50 p-4">
                    <p class="text-xs uppercase tracking-wider text-gray-500">Ihr Alter</p>
                    <p id="age-main" class="mt-1 text-2xl font-black text-[var(--brand)]"></p>
                </div>

                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-md bg-gray-50 p-4">
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Gelebte Tage</dt>
                        <dd id="age-days" class="mt-1 text-lg font-bold"></dd>
                    </div>
                    <div class="rounded-md bg-gray-50 p-4">
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Geboren an einem</dt>
                        <dd id="age-weekday" class="mt-1 text-lg font-bold"></dd>
                    </div>
                    <div class="rounded-md bg-gray-50 p-4">
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Nächster Geburtstag</dt>
                        <dd id="age-next" class="mt-1 text-lg font-bold"></dd>
                    </div>
                    <div class="rounded-md bg-gray-50 p-4">
                        <dt class="text-xs uppercase tracking-wider text-gray-500">Noch</dt>
                        <dd id="age-countdown" class="mt-1 text-lg font-bold"></dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="mt-8 text-sm leading-relaxed text-gray-600">
            <h2 class="text-base font-bold text-gray-900">Wie wird das Alter berechnet?</h2>
            <p class="mt-2">
                Gezählt werden volle Jahre, dann die vollen Monate seit dem letzten
                Geburtstag und zuletzt die übrigen Tage. Ein am 29. Februar Geborener
                hat in einem Nicht-Schaltjahr am 1. März Geburtstag — so rechnet auch
                diese Seite.
            </p>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('birthdate');
        if (!input) return;

        var result = document.getElementById('age-result');
        var error  = document.getElementById('age-error');

        var weekdays = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];

        function text(id, value) { document.getElementById(id).textContent = value; }

        function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

        function show(message) {
            error.textContent = message;
            error.classList.remove('hidden');
            result.classList.add('hidden');
        }

        function calculate() {
            if (!input.value) {
                error.classList.add('hidden');
                result.classList.add('hidden');
                return;
            }

            var parts = input.value.split('-');
            var birth = new Date(+parts[0], +parts[1] - 1, +parts[2]);

            // Compare dates only — the time of day would make "today" wrong.
            var now   = new Date();
            var today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

            if (isNaN(birth.getTime()) || birth > today) {
                show('Bitte geben Sie ein Datum in der Vergangenheit ein.');
                return;
            }

            // Borrow days from the previous month, then months from the year.
            var years  = today.getFullYear() - birth.getFullYear();
            var months = today.getMonth() - birth.getMonth();
            var days   = today.getDate() - birth.getDate();

            if (days < 0) {
                months -= 1;
                days += new Date(today.getFullYear(), today.getMonth(), 0).getDate();
            }
            if (months < 0) {
                years -= 1;
                months += 12;
            }

            var lived = Math.round((today - birth) / 86400000);

            // A 29 February birthday lands on 1 March in a common year.
            var next = new Date(today.getFullYear(), birth.getMonth(), birth.getDate());
            if (next < today) {
                next = new Date(today.getFullYear() + 1, birth.getMonth(), birth.getDate());
            }
            var until = Math.round((next - today) / 86400000);

            text('age-main', plural(years, 'Jahr', 'Jahre') + ', ' +
                             plural(months, 'Monat', 'Monate') + ' und ' +
                             plural(days, 'Tag', 'Tage'));
            text('age-days', lived.toLocaleString('de-DE') + ' Tage');
            text('age-weekday', weekdays[birth.getDay()]);
            text('age-next', next.toLocaleDateString('de-DE', { day: '2-digit', month: 'long', year: 'numeric' }));
            text('age-countdown', until === 0 ? 'Heute! Herzlichen Glückwunsch.' : plural(until, 'Tag', 'Tage'));

            error.classList.add('hidden');
            result.classList.remove('hidden');
        }

        input.addEventListener('input', calculate);
        input.addEventListener('change', calculate);
        calculate();
    })();
</script>
@endpush
