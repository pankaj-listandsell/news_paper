@extends('layouts.app')

@section('title', ($article->meta_title ?: $article->title) . ' — ' . config('app.name'))
@section('meta_description', $article->meta_description ?: $article->excerpt)

@section('content')
    <div class="grid gap-10 lg:grid-cols-3">
        <article class="lg:col-span-2">
            @if ($article->category)
                <a href="{{ route('category.show', $article->category) }}"
                   class="text-sm font-bold uppercase tracking-wide text-red-600">{{ $article->category->name }}</a>
            @endif
            <h1 class="mt-2 text-3xl font-black leading-tight md:text-4xl">{{ $article->title }}</h1>
            @if ($article->subtitle)
                <p class="mt-3 text-lg text-gray-600">{{ $article->subtitle }}</p>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                <a href="{{ route('author.show', $article->author) }}" class="font-semibold text-gray-700 hover:text-red-600">{{ $article->author?->name }}</a>
                <span>&middot;</span>
                <span>{{ $article->published_at?->translatedFormat('d F Y, H:i') }}</span>
                <span>&middot;</span>
                <span>{{ $article->reading_time }} min read</span>
                <span>&middot;</span>
                <span>{{ number_format($article->views) }} views</span>
            </div>

            <img src="{{ $article->image_url }}" alt="{{ $article->title }}"
                 class="mt-6 w-full rounded-xl object-cover">

            <div class="prose prose-lg mt-6 max-w-none prose-a:text-red-600 prose-img:rounded-lg">
                {!! $article->body !!}
            </div>

            @if ($article->tags->isNotEmpty())
                <div class="mt-8 flex flex-wrap gap-2">
                    @foreach ($article->tags as $tag)
                        <a href="{{ route('tag.show', $tag) }}"
                           class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-red-50 hover:text-red-600">#{{ $tag->name }}</a>
                    @endforeach
                </div>
            @endif

            {{-- Comments --}}
            <section class="mt-12">
                <h2 class="text-xl font-black">Comments ({{ $article->comments->count() }})</h2>

                @if (session('comment_status'))
                    <div class="mt-4 rounded-md bg-green-50 p-3 text-sm text-green-700">{{ session('comment_status') }}</div>
                @endif

                <div class="mt-6 space-y-6">
                    @forelse ($article->comments as $comment)
                        <div class="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold">{{ $comment->author_name }}</span>
                                <span class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-2 text-sm text-gray-700">{{ $comment->body }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">Abhi tak koi comment nahi. Pehle aap likhein!</p>
                    @endforelse
                </div>

                {{-- Comment form --}}
                <form action="{{ route('comments.store', $article) }}" method="POST" class="mt-8 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    @csrf
                    <h3 class="font-bold">Leave a comment</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <input type="text" name="author_name" value="{{ old('author_name') }}" placeholder="Your name"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none">
                            @error('author_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <input type="email" name="author_email" value="{{ old('author_email') }}" placeholder="Your email"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none">
                            @error('author_email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <textarea name="body" rows="4" placeholder="Your comment..."
                              class="mt-4 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-red-500 focus:outline-none">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="mt-4 rounded-md bg-red-600 px-5 py-2 text-sm font-semibold text-white hover:bg-red-700">Post comment</button>
                </form>
            </section>
        </article>

        {{-- Sidebar: related --}}
        <aside>
            <h2 class="mb-4 border-b-2 border-red-600 pb-2 text-lg font-black uppercase">Related</h2>
            <div class="space-y-6">
                @foreach ($related as $item)
                    @include('partials.article-card', ['article' => $item])
                @endforeach
            </div>
        </aside>
    </div>
@endsection
