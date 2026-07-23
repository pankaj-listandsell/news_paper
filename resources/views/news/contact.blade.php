@extends('layouts.app')

@section('title', 'Kontakt — ' . \App\Support\SiteSettings::name())
@section('meta_description', 'Nehmen Sie Kontakt mit der Redaktion von ' . \App\Support\SiteSettings::name() . ' auf — Fragen, Hinweise und Themenvorschläge sind willkommen.')

@section('content')
    {{-- Breadcrumb --}}
    <nav aria-label="Breadcrumb" class="mb-4 text-xs text-gray-500">
        <ol class="flex flex-wrap items-center gap-1.5">
            <li><a href="{{ route('home') }}" class="hover:text-[var(--brand)]">Startseite</a></li>
            <li aria-hidden="true" class="text-gray-300">›</li>
            <li class="text-gray-700" aria-current="page">Kontakt</li>
        </ol>
    </nav>

    <header class="mb-6 border-b-2 border-[var(--brand)] pb-3">
        <h1 class="text-2xl font-black uppercase">Kontakt</h1>
    </header>

    <div class="max-w-2xl">
        <p class="text-sm text-gray-600">
            Sie haben eine Frage, einen Hinweis oder einen Themenvorschlag? Schreiben Sie uns über das
            Formular — wir melden uns so schnell wie möglich bei Ihnen.
        </p>

        @if (session('contact_status'))
            <div class="mt-6 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('contact_status') }}</div>
        @endif

        @if (session('contact_error'))
            <div class="mt-6 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ session('contact_error') }}</div>
        @endif

        <form action="{{ route('contact.send') }}" method="POST"
              class="mt-6 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
            @csrf

            {{-- Spam trap: hidden from people, tempting to bots. Never remove. --}}
            <div class="absolute left-[-9999px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                <label for="hp_website">Website (bitte leer lassen)</label>
                <input type="text" id="hp_website" name="website" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="form_started_at" value="{{ encrypt(time()) }}">

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1 block text-sm font-semibold text-gray-700">Name</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" required
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="mb-1 block text-sm font-semibold text-gray-700">E-Mail</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required
                           class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">
                    @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-4">
                <label for="subject" class="mb-1 block text-sm font-semibold text-gray-700">Betreff</label>
                <input type="text" id="subject" name="subject" value="{{ old('subject') }}" required
                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">
                @error('subject')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4">
                <label for="message" class="mb-1 block text-sm font-semibold text-gray-700">Nachricht</label>
                <textarea id="message" name="message" rows="6" required
                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            @include('partials.recaptcha')

            <p class="mt-4 text-xs text-gray-500">
                Mit dem Absenden stimmen Sie zu, dass wir Ihre Angaben zur Beantwortung Ihrer Anfrage verarbeiten.
                Weitere Informationen in unserer
                <a href="{{ route('page', 'datenschutz') }}" class="underline hover:text-[var(--brand)]">Datenschutzerklärung</a>.
            </p>

            <button type="submit"
                    class="mt-5 rounded-md bg-[var(--brand)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">
                Nachricht senden
            </button>
        </form>
    </div>
@endsection
