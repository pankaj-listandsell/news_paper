@if (\App\Support\SiteSettings::recaptchaEnabled())
    <div class="mt-4">
        <div class="g-recaptcha" data-sitekey="{{ \App\Support\SiteSettings::recaptchaSiteKey() }}"></div>
        @error('g-recaptcha-response')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Loaded once per page, in German. --}}
    @once
        @push('scripts')
            <script src="https://www.google.com/recaptcha/api.js?hl=de" async defer></script>
        @endpush
    @endonce
@endif
