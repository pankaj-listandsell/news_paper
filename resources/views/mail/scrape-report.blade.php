<x-mail::message>
# News scrape finished

@php
    // Built in PHP: a Blade directive glued to a word (…updated@if) is not compiled.
    $summary = "**{$totalCreated}** new article(s), **{$totalUpdated}** updated";

    if ($failures > 0) {
        $summary .= ", **{$failures}** source(s) failed";
    }
@endphp

{{ $summary }}.

<x-mail::table>
| Source | New | Updated | Status |
|:-------|:---:|:-------:|:-------|
@foreach($rows as $row)
| {{ $row['source'] }} | {{ $row['created'] }} | {{ $row['updated'] }} | {{ $row['error'] ? '⚠ ' . $row['error'] : 'OK' }} |
@endforeach
</x-mail::table>

<x-mail::button :url="url('/admin')">
Open admin panel
</x-mail::button>

You are receiving this because “Email me a summary after each run” is on in General Settings. Turn it off there to stop these emails.

{{ config('app.name') }}
</x-mail::message>
