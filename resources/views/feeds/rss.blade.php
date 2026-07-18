<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title>{{ $siteName }}</title>
        <link>{{ route('home') }}</link>
        <description>{{ $siteDesc }}</description>
        <language>de-DE</language>
        <lastBuildDate>{{ now()->toRssString() }}</lastBuildDate>
        <atom:link href="{{ route('rss') }}" rel="self" type="application/rss+xml"/>

        @foreach ($articles as $article)
            <item>
                <title>{{ $article->title }}</title>
                <link>{{ route('article.show', $article) }}</link>
                <guid isPermaLink="true">{{ route('article.show', $article) }}</guid>
                <pubDate>{{ $article->published_at?->toRssString() }}</pubDate>
                @if ($article->category)
                    <category>{{ $article->category->name }}</category>
                @endif
                <description>{{ $article->excerpt }}</description>
                @if ($article->featured_image)
                    <enclosure url="{{ $article->image_url }}" type="image/jpeg"/>
                @endif
            </item>
        @endforeach
    </channel>
</rss>
