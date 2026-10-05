<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Clearing the caches from the admin panel.
 *
 * Deploys here happen over FTP, where there is no terminal to run artisan in.
 * Laravel decides a compiled page template is still good by comparing file
 * times, and FTP often leaves the uploaded file looking older than the
 * compiled copy — so an upload can appear to do nothing at all until the
 * compiled copies are thrown away.
 */
class ManageCache extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Cache';

    protected static ?string $title = 'Cache';

    protected static ?int $navigationSort = 9;

    protected static string $view = 'filament.pages.manage-cache';

    /** Clearing caches is a deployment job, not an editor's. */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('clearViews')
                ->label('Clear page templates')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->action(fn () => $this->run(['view:clear'], 'Page templates cleared')),

            Action::make('clearData')
                ->label('Clear stored data')
                ->icon('heroicon-o-circle-stack')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Settings and the weather reading are read again from scratch. Nothing is lost.')
                ->action(fn () => $this->run(['cache:clear'], 'Stored data cleared')),

            Action::make('clearEverything')
                ->label('Clear everything')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear every cache')
                ->modalDescription('Use this after uploading files. The first page load afterwards is a little slower while everything is rebuilt.')
                ->action(fn () => $this->run([
                    'view:clear',
                    'cache:clear',
                    'config:clear',
                    'route:clear',
                    'filament:optimize-clear',
                ], 'Everything cleared')),
        ];
    }

    /**
     * Run the given artisan commands, reporting what actually worked.
     *
     * Each is tried on its own: on shared hosting one of these can fail on a
     * permission while the rest are fine, and clearing four out of five is
     * still worth having — as long as it is not reported as a clean sweep.
     *
     * @param  array<int, string>  $commands
     */
    private function run(array $commands, string $title): void
    {
        $failed = [];

        foreach ($commands as $command) {
            try {
                Artisan::call($command);
            } catch (\Throwable $e) {
                $failed[] = $command;
                Log::warning("Cache clear failed for {$command}: {$e->getMessage()}");
            }
        }

        if ($failed === []) {
            Notification::make()->title($title)->success()->send();

            return;
        }

        Notification::make()
            ->title('Some caches could not be cleared')
            ->body(implode(', ', $failed) . ' — most likely a file permission on the server. The rest were cleared.')
            ->danger()
            ->persistent()
            ->send();
    }
}
