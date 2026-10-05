<x-filament-panels::page>
    <div class="prose max-w-none dark:prose-invert">
        <p>
            Use these after uploading files to the server. Laravel keeps compiled
            copies of your pages, and an upload does not always convince it to
            rebuild them — clearing is what makes a change actually show up.
        </p>

        <ul>
            <li>
                <strong>Clear page templates</strong> — the one to reach for after
                changing anything the visitor sees. Safe to run at any time.
            </li>
            <li>
                <strong>Clear stored data</strong> — settings and the weather
                reading. They are read again on the next page load.
            </li>
            <li>
                <strong>Clear everything</strong> — the above plus configuration,
                routes and the admin panel's own cache. Use this after a bigger
                upload, or when a new page is missing from the menu.
            </li>
        </ul>

        <p>
            Nothing here deletes content. Articles, settings and uploads are all
            untouched; only the copies Laravel keeps to save itself work.
        </p>
    </div>
</x-filament-panels::page>
