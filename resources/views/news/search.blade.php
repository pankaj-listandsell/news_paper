@extends('layouts.app')

@section('title', 'Suche — ' . \App\Support\SiteSettings::name())

@section('content')
    <header class="mb-6 border-b-2 border-red-600 pb-3">
        <h1 class="text-2xl font-black">Suchergebnisse</h1>
        @if ($q !== '')
            <p class="mt-1 text-gray-600">{{ $articles->total() }} Ergebnisse für „<span class="font-semibold">{{ $q }}</span>"</p>
        @else
            <p class="mt-1 text-gray-600">Geben Sie oben einen Suchbegriff ein.</p>
        @endif
    </header>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="text-gray-500">Keine Ergebnisse gefunden.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $articles->links() }}</div>
@endsection
