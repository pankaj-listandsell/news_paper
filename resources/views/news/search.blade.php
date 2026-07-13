@extends('layouts.app')

@section('title', 'Search — ' . config('app.name'))

@section('content')
    <header class="mb-6 border-b-2 border-red-600 pb-3">
        <h1 class="text-2xl font-black">Search results</h1>
        @if ($q !== '')
            <p class="mt-1 text-gray-600">"<span class="font-semibold">{{ $q }}</span>" ke liye {{ $articles->total() }} results</p>
        @else
            <p class="mt-1 text-gray-600">Search karne ke liye upar box me kuch likhein.</p>
        @endif
    </header>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($articles as $article)
            @include('partials.article-card', ['article' => $article])
        @empty
            <p class="text-gray-500">Koi result nahi mila.</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $articles->links() }}</div>
@endsection
