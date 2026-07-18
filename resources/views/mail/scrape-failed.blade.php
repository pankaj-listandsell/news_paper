<x-mail::message>
# Scrape failed

The news source **{{ $source->name }}** could not be scraped.

**Feed:** {{ $source->feed_url }}
**Last successful run:** {{ $source->last_scraped_at?->diffForHumans() ?? 'never' }}

**Error:**

<x-mail::panel>
{{ $error }}
</x-mail::panel>

The source stays active and will be retried on the next scheduled run. If it keeps
failing, check the feed URL or switch the source off.

<x-mail::button :url="url('/admin/news-sources/' . $source->id . '/edit')">
Open source settings
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
