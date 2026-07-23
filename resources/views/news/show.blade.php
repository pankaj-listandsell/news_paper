@extends('layouts.app')

@section('title', ($article->meta_title ?: $article->title) . ' — ' . \App\Support\SiteSettings::name())
@section('meta_description', $article->meta_description ?: $article->excerpt)

{{-- Link preview: real headline + article image --}}
@section('og_type', 'article')
@section('og_title', $article->meta_title ?: $article->title)
@section('og_image', $article->image_url)

@push('meta')
    <meta property="article:published_time" content="{{ $article->published_at?->toIso8601String() }}">
    @if ($article->category)
        <meta property="article:section" content="{{ $article->category->name }}">
    @endif
    @foreach ($article->tags as $tag)
        <meta property="article:tag" content="{{ $tag->name }}">
    @endforeach

    {{-- Structured data — Google News / rich results --}}
    <script type="application/ld+json">
    @php
        $ld = [
            '@context'         => 'https://schema.org',
            '@type'            => 'NewsArticle',
            'headline'         => Str::limit($article->title, 110, ''),
            'description'      => $article->meta_description ?: $article->excerpt,
            'image'            => [$article->image_url],
            'datePublished'    => $article->published_at?->toIso8601String(),
            'dateModified'     => $article->updated_at?->toIso8601String(),
            'author'           => [
                '@type' => 'Person',
                'name'  => $article->byline,
            ],
            'publisher'        => [
                '@type' => 'Organization',
                'name'  => \App\Support\SiteSettings::name(),
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => \App\Support\SiteSettings::logoUrl() ?? asset('favicon.ico'),
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id'   => route('article.show', $article),
            ],
            'articleSection'   => $article->category?->name,
            'keywords'         => $article->tags->pluck('name')->implode(', '),
        ];
    @endphp
    {!! json_encode(array_filter($ld), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    {{-- Breadcrumbs — rich results in Google --}}
    <script type="application/ld+json">
    @php
        $crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Startseite', 'item' => route('home')]];
        $pos = 2;
        if ($article->category) {
            $crumbs[] = ['@type' => 'ListItem', 'position' => $pos++, 'name' => $article->category->name, 'item' => route('category.show', $article->category)];
        }
        $crumbs[] = ['@type' => 'ListItem', 'position' => $pos, 'name' => $article->title, 'item' => route('article.show', $article)];
        $breadcrumbLd = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];
    @endphp
    {!! json_encode($breadcrumbLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>
@endpush

@section('content')
    {{-- Reading progress bar --}}
    <div id="reading-progress"
         class="fixed inset-x-0 top-0 z-50 h-1 origin-left scale-x-0 bg-[var(--brand)] transition-transform duration-75"></div>

    <div class="grid gap-10 lg:grid-cols-3">
        <article id="article-body" class="lg:col-span-2">
            {{-- Breadcrumb --}}
            <nav aria-label="Breadcrumb" class="mb-4 text-xs text-gray-500">
                <ol class="flex flex-wrap items-center gap-1.5">
                    <li><a href="{{ route('home') }}" class="hover:text-[var(--brand)]">Startseite</a></li>
                    @if ($article->category)
                        <li aria-hidden="true" class="text-gray-300">›</li>
                        <li><a href="{{ route('category.show', $article->category) }}" class="hover:text-[var(--brand)]">{{ $article->category->name }}</a></li>
                    @endif
                    <li aria-hidden="true" class="text-gray-300">›</li>
                    <li class="max-w-[16rem] truncate text-gray-700" aria-current="page">{{ $article->title }}</li>
                </ol>
            </nav>

            @if ($article->category)
                <a href="{{ route('category.show', $article->category) }}"
                   class="text-sm font-bold uppercase tracking-wide text-[var(--brand)]">{{ $article->category->name }}</a>
            @endif
            <h1 class="mt-2 text-3xl font-black leading-tight md:text-4xl">{{ $article->title }}</h1>
            @if ($article->subtitle)
                <p class="mt-3 text-lg text-gray-600">{{ $article->subtitle }}</p>
            @endif

            <div class="mt-4 flex flex-wrap items-center gap-2 text-sm text-gray-500">
                @if ($article->author)
                    @if ($article->author->show_on_frontend)
                        <a href="{{ route('author.show', $article->author) }}" class="font-semibold text-gray-700 hover:text-[var(--brand)]">{{ $article->author->name }}</a>
                    @else
                        <span class="font-semibold text-gray-700">{{ $article->author->name }}</span>
                    @endif
                @else
                    <span class="font-semibold text-gray-700">Redaktion</span>
                @endif
                <span>&middot;</span>
                <span>{{ $article->published_at?->locale('de')->translatedFormat('d. F Y, H:i') }} Uhr</span>
                <span>&middot;</span>
                <span>{{ $article->reading_time }} Min. Lesezeit</span>
                <span>&middot;</span>
                <span>{{ number_format($article->views) }} Aufrufe</span>
            </div>

            <img src="{{ $article->image_url }}" alt="{{ $article->title }}"
                 class="mt-6 w-full rounded-xl object-cover">

            <div class="prose prose-lg mt-6 max-w-none prose-a:text-[var(--brand)] prose-img:rounded-lg">
                {!! $article->body !!}
            </div>

            @include('partials.share-buttons', ['article' => $article])

            @if ($article->tags->isNotEmpty())
                <div class="mt-8 flex flex-wrap gap-2">
                    @foreach ($article->tags as $tag)
                        <a href="{{ route('tag.show', $tag) }}"
                           class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]">#{{ $tag->name }}</a>
                    @endforeach
                </div>
            @endif

            {{-- Comments --}}
            @if (\App\Support\SiteSettings::commentsEnabled())
            <section class="mt-12">
                <h2 class="text-xl font-black">Kommentare ({{ $article->comments->count() }})</h2>

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
                        <p class="text-sm text-gray-500">Noch keine Kommentare. Schreiben Sie den ersten!</p>
                    @endforelse
                </div>

                {{-- Comment form --}}
                <form action="{{ route('comments.store', $article) }}" method="POST" class="mt-8 rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-100">
                    @csrf
                    <h3 class="font-bold">Kommentar schreiben</h3>

                    {{-- Spam trap: hidden from people, tempting to bots. Never remove. --}}
                    <div class="absolute left-[-9999px] top-auto h-px w-px overflow-hidden" aria-hidden="true">
                        <label for="hp_website">Website (bitte leer lassen)</label>
                        <input type="text" id="hp_website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <input type="hidden" name="form_started_at" value="{{ encrypt(time()) }}">
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <input type="text" name="author_name" value="{{ old('author_name') }}" placeholder="Ihr Name"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">
                            @error('author_name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <input type="email" name="author_email" value="{{ old('author_email') }}" placeholder="Ihre E-Mail"
                                   class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">
                            @error('author_email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <textarea name="body" rows="4" placeholder="Ihr Kommentar..."
                              class="mt-4 w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[var(--brand)] focus:outline-none">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="mt-4 rounded-md bg-[var(--brand)] px-5 py-2 text-sm font-semibold text-white hover:bg-[var(--brand-dark)]">Kommentar absenden</button>
                </form>
            </section>
            @endif
        </article>

        {{-- Sidebar: related --}}
        <aside class="lg:sticky lg:top-4 lg:self-start">
            <h2 class="mb-4 border-b-2 border-[var(--brand)] pb-2 text-lg font-black uppercase">Ähnliche Artikel</h2>
            <div class="space-y-6">
                @foreach ($related as $item)
                    @include('partials.article-card', ['article' => $item])
                @endforeach
            </div>
        </aside>
    </div>

    @push('scripts')
        <script>
            (function () {
                const bar     = document.getElementById('reading-progress');
                const article = document.getElementById('article-body');
                if (!bar || !article) return;

                const update = () => {
                    const start = article.offsetTop;
                    const total = article.offsetHeight - window.innerHeight;
                    const done  = Math.min(Math.max((window.scrollY - start) / total, 0), 1);
                    bar.style.transform = 'scaleX(' + done + ')';
                };

                update();
                window.addEventListener('scroll', update, { passive: true });
                window.addEventListener('resize', update, { passive: true });
            })();
        </script>
    @endpush
@endsection
