{{--
    The thin strip above the header: today's date, a live clock, Berlin's
    weather and air quality, and the social accounts.

    Styled inline rather than with Tailwind classes on purpose — the
    stylesheet is a prebuilt bundle that deployment does not rebuild, so a
    class that is new here would have no rules behind it in production.

    The clock is set by script rather than rendered server-side: article pages
    answer repeat visits with a 304, so a time baked into the HTML would be
    the time the page was first built, not now.
--}}
<div style="background:#111827;color:#d1d5db;font:500 12.5px/1.4 Helvetica,Arial,sans-serif;border-bottom:1px solid #1f2937">
    <div style="max-width:80rem;margin:0 auto;padding:7px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">

        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            {{-- Numeric, because the app locale is English and a month name
                 rendered here would read "October" on a German site for the
                 moment before the script below replaces it. --}}
            <span id="hr-clock" style="white-space:nowrap">{{ now()->timezone('Europe/Berlin')->format('d.m.Y') }}</span>

            @if ($weather)
                <span style="white-space:nowrap;color:#9ca3af">|</span>
                <span style="white-space:nowrap">
                    <strong style="color:#fff;font-weight:700">Berlin</strong>
                    <span aria-hidden="true">{{ $weather['icon'] }}</span>
                    <strong style="color:#fff;font-weight:700">{{ $weather['temperature'] }}&nbsp;°C</strong>
                    <span style="color:#9ca3af">{{ $weather['label'] }}</span>
                </span>

                @if ($weather['aqi'] !== null)
                    <span style="white-space:nowrap;color:#9ca3af">|</span>
                    <span style="white-space:nowrap" title="Luftqualität: {{ $weather['aqi_label'] }}">
                        AQI
                        <strong style="color:#fff;font-weight:700">{{ $weather['aqi'] }}</strong>
                        <span style="color:#9ca3af">{{ $weather['aqi_label'] }}</span>
                    </span>
                @endif
            @endif
        </div>

        @if (count($siteSocial))
            {{-- Marks only, with the name on the link for anyone who cannot
                 see them. --}}
            <div style="display:flex;align-items:center;gap:14px">
                @foreach ($siteSocial as $label => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener"
                       title="{{ $label }}" aria-label="{{ $site['site_name'] }} auf {{ $label }}"
                       style="color:#d1d5db;text-decoration:none;display:inline-flex;align-items:center">
                        @include('partials.social-icon', ['label' => $label, 'size' => 16])
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

<script>
    // Berlin time, kept right in the reader's browser rather than frozen at
    // the moment the page was built.
    (function () {
        var el = document.getElementById('hr-clock');
        if (!el) return;

        function tick() {
            try {
                el.textContent = new Intl.DateTimeFormat('de-DE', {
                    day: '2-digit', month: 'long', year: 'numeric',
                    hour: '2-digit', minute: '2-digit',
                    timeZone: 'Europe/Berlin',
                }).format(new Date()) + ' Uhr';
            } catch (e) {
                // Leave the server-rendered date in place.
            }
        }

        tick();
        setInterval(tick, 30000);
    })();
</script>
