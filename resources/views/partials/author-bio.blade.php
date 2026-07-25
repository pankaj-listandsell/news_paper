{{-- Author intro card for the article detail page. Expects $author (a User). --}}
@if ($author)
    <section class="mt-10 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200/70">
        {{-- Brand accent strip --}}
        <div class="h-1.5 w-full bg-gradient-to-r from-[var(--brand)] to-[var(--brand-dark)]"></div>

        <div class="p-6 sm:p-7">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                <img src="{{ $author->avatar_url }}" alt="{{ $author->name }}"
                     class="h-20 w-20 shrink-0 rounded-full object-cover ring-4 ring-[var(--brand-soft)]">

                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-bold uppercase tracking-widest text-[var(--brand)]">Über den Autor</p>

                    <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1.5">
                        @if ($author->show_on_frontend)
                            <a href="{{ route('author.show', $author) }}"
                               class="text-2xl font-black leading-tight text-gray-900 hover:text-[var(--brand)]">{{ $author->name }}</a>
                        @else
                            <span class="text-2xl font-black leading-tight text-gray-900">{{ $author->name }}</span>
                        @endif

                        @if ($author->designation)
                            <span class="rounded-full bg-[var(--brand-soft)] px-2.5 py-0.5 text-xs font-semibold text-[var(--brand)]">{{ $author->designation }}</span>
                        @endif
                    </div>

                    @if ($author->bio)
                        <p class="mt-3 line-clamp-4 max-w-2xl text-sm leading-relaxed text-gray-600">{{ $author->bio }}</p>
                    @endif

                    @if ($author->show_on_frontend || filled($author->profile_links))
                        <div class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-gray-100 pt-4 text-sm">
                            @if ($author->show_on_frontend)
                                <a href="{{ route('author.show', $author) }}"
                                   class="inline-flex items-center gap-1 font-semibold text-[var(--brand)] hover:gap-2 hover:underline">
                                    Alle Artikel ansehen <span aria-hidden="true">→</span>
                                </a>
                            @endif

                            @foreach ($author->profile_links as $label => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener"
                                   class="font-semibold text-gray-500 transition hover:text-[var(--brand)]">{{ $label }} ↗</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
@endif
