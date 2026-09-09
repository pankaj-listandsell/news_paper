<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Social\PublisherFactory;
use App\Support\SocialSettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Where the three social accounts are connected and the scheduled run is
 * configured.
 *
 * Credentials live on the SocialAccount rows (encrypted); everything else is
 * an ordinary setting. Secrets are never sent back to the browser — a saved
 * token shows as a placeholder, and leaving the field empty keeps it.
 */
class ManageSocialSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-share';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Social Posting';

    protected static ?string $title = 'Social Posting';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.pages.manage-social-settings';

    /** Stands in for a stored secret, so the real one never reaches the page. */
    private const KEPT = '__unchanged__';

    public ?array $data = [];

    /**
     * Which credential each platform needs. Key => label.
     *
     * @var array<string, array<string, string>>
     */
    public const CREDENTIALS = [
        'facebook' => [
            'page_id'      => 'Page ID',
            'access_token' => 'Page access token',
        ],
        'linkedin' => [
            'organization_id' => 'Organisation ID',
            'access_token'    => 'Access token',
        ],
        'x' => [
            'api_key'             => 'API key',
            'api_secret'          => 'API key secret',
            'access_token'        => 'Access token',
            'access_token_secret' => 'Access token secret',
        ],
    ];

    public function mount(): void
    {
        $values = [
            'social_practice_mode'   => SocialSettings::practiceMode(),
            'social_batch_size'      => SocialSettings::batchSize(),
            'social_frequency'       => SocialSettings::frequency(),
            'social_schedule_times'  => implode(', ', SocialSettings::scheduleTimes()),
            'social_backlog_cutoff'  => Setting::get('social_backlog_cutoff'),
        ];

        foreach (SocialAccount::PLATFORMS as $platform => $label) {
            $account = SocialAccount::firstOrNew(['platform' => $platform]);

            $values["{$platform}_name"]      = $account->name;
            $values["{$platform}_is_active"] = (bool) $account->is_active;
            $values["{$platform}_auto_post"] = (bool) $account->auto_post;
            $values["{$platform}_token_expires_at"] = $account->token_expires_at;

            foreach (array_keys(self::CREDENTIALS[$platform]) as $key) {
                // Show a placeholder for something already saved, never the value.
                $values["{$platform}_{$key}"] = filled($account->credential($key)) ? self::KEPT : null;
            }
        }

        $this->form->fill($values);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('How posting runs')
                    ->description('These apply to every platform.')
                    ->schema([
                        Forms\Components\Toggle::make('social_practice_mode')
                            ->label('Practice mode')
                            ->helperText('On: nothing leaves the server. Everything is recorded exactly as a real post would be, but the message only goes to the log. Use this to try the whole flow before connecting any account.'),
                        Forms\Components\TextInput::make('social_batch_size')
                            ->label('Posts per run, per platform')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->helperText('Keep this small. A sudden burst of posts reads as spam.'),
                        Forms\Components\DatePicker::make('social_backlog_cutoff')
                            ->label('Never auto-post anything published before')
                            ->native(false)
                            ->helperText('Protects the back catalogue. Without a date, switching auto-posting on would try to push every article the site has ever published.'),
                        Forms\Components\Select::make('social_frequency')
                            ->label('When to run')
                            ->options([
                                'times'      => 'At set times of day',
                                'hourly'     => 'Every hour',
                                'every_30'   => 'Every 30 minutes',
                            ])
                            ->native(false)
                            ->live(),
                        Forms\Components\TextInput::make('social_schedule_times')
                            ->label('Times of day')
                            ->placeholder('09:00, 15:00, 20:00')
                            ->helperText('German local time, comma separated.')
                            ->visible(fn (Forms\Get $get): bool => $get('social_frequency') === 'times'),
                    ])->columns(2),

                ...$this->platformSections(),
            ])
            ->statePath('data');
    }

    /**
     * @return array<int, Forms\Components\Section>
     */
    private function platformSections(): array
    {
        $factory  = new PublisherFactory();
        $sections = [];

        foreach (SocialAccount::PLATFORMS as $platform => $label) {
            $account = SocialAccount::firstOrNew(['platform' => $platform]);

            $fields = [
                Forms\Components\TextInput::make("{$platform}_name")
                    ->label('Account name')
                    ->placeholder('Hauptstadt Report')
                    ->helperText('Only used to label this account in the admin panel.'),
                Forms\Components\Toggle::make("{$platform}_is_active")
                    ->label('Connected')
                    ->helperText('Off means this platform is ignored everywhere.'),
                Forms\Components\Toggle::make("{$platform}_auto_post")
                    ->label('Include in the scheduled run')
                    ->helperText('Off means it can still be posted to by hand.'),
                Forms\Components\DateTimePicker::make("{$platform}_token_expires_at")
                    ->label('Token expires')
                    ->native(false)
                    ->helperText('Optional. Set it and the panel warns you before posting quietly stops.'),
            ];

            foreach (self::CREDENTIALS[$platform] as $key => $credentialLabel) {
                $fields[] = Forms\Components\TextInput::make("{$platform}_{$key}")
                    ->label($credentialLabel)
                    ->password()
                    ->revealable()
                    ->autocomplete(false)
                    ->helperText(filled($account->credential($key)) ? 'Saved. Leave as is to keep it.' : null);
            }

            $sections[] = Forms\Components\Section::make($label)
                ->description($this->statusLine($account, $factory))
                ->collapsed(fn (): bool => ! $account->is_active)
                ->schema($fields)
                ->columns(2);
        }

        return $sections;
    }

    private function statusLine(SocialAccount $account, PublisherFactory $factory): string
    {
        if (! $account->exists || ! $account->is_active) {
            return 'Not connected.';
        }

        if ($account->tokenHasExpired()) {
            return 'The access token has expired — reconnect this account.';
        }

        $publisher = $factory->for($account->platform);

        if ($publisher && ! $publisher->isConfigured($account)) {
            return 'Still missing: ' . implode(', ', $publisher->missing($account)) . '.';
        }

        $when = $account->last_posted_at?->diffForHumans();

        return $when ? "Connected. Last posted {$when}." : 'Connected. Nothing posted yet.';
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Setting::set('social_practice_mode', $state['social_practice_mode'] ? '1' : '0');
        Setting::set('social_batch_size', (string) $state['social_batch_size']);
        Setting::set('social_frequency', $state['social_frequency']);
        Setting::set('social_schedule_times', $state['social_schedule_times'] ?? '');
        Setting::set('social_backlog_cutoff', $state['social_backlog_cutoff'] ?? '');

        foreach (SocialAccount::PLATFORMS as $platform => $label) {
            $account = SocialAccount::firstOrNew(['platform' => $platform]);

            $account->fill([
                'platform'         => $platform,
                'name'             => $state["{$platform}_name"] ?? null,
                'is_active'        => (bool) ($state["{$platform}_is_active"] ?? false),
                'auto_post'        => (bool) ($state["{$platform}_auto_post"] ?? false),
                'token_expires_at' => $state["{$platform}_token_expires_at"] ?? null,
            ]);

            // Merge rather than replace: a field left at the placeholder means
            // "keep what is already stored", so a saved token survives a save
            // that only changed a toggle.
            $credentials = $account->credentials();

            foreach (array_keys(self::CREDENTIALS[$platform]) as $key) {
                $value = $state["{$platform}_{$key}"] ?? null;

                if ($value === self::KEPT) {
                    continue;
                }

                if (blank($value)) {
                    unset($credentials[$key]);
                } else {
                    $credentials[$key] = trim($value);
                }
            }

            $account->setCredentials($credentials);
            $account->save();
        }

        Notification::make()
            ->title('Social settings saved')
            ->success()
            ->send();

        // Re-read, so placeholders and status lines reflect what is now stored.
        $this->mount();
    }
}
