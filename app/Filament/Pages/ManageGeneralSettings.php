<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\SiteSettings;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;

class ManageGeneralSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'General Settings';

    protected static ?string $title = 'General Settings';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.pages.manage-general-settings';

    public ?array $data = [];

    /**
     * "Send test email" button in the page header — same action as the one
     * inside the SMTP section, kept here for quick access.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTestEmailHeader')
                ->label('Send test email')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->modalDescription('Sends a test message using the saved SMTP settings. Save your changes first.')
                ->modalSubmitActionLabel('Send')
                ->form([
                    Forms\Components\TextInput::make('test_to')
                        ->label('Send to')
                        ->email()
                        ->required()
                        ->default(fn () => SiteSettings::notifyRecipient() ?: 'pankajlistandsell@gmail.com'),
                ])
                ->action(fn (array $data) => $this->sendTestEmail($data['test_to'])),
        ];
    }

    /**
     * Send a one-line test email using the currently saved SMTP settings and
     * report the result as a notification. Shared by the header button and the
     * button inside the "Email (SMTP)" section.
     */
    public function sendTestEmail(?string $to = null): void
    {
        $to = $to ?: SiteSettings::notifyRecipient();

        if (blank($to)) {
            Notification::make()
                ->title('No recipient')
                ->body('Enter an address to send the test to.')
                ->warning()
                ->send();

            return;
        }

        // Use the latest saved SMTP settings for this send.
        SiteSettings::applyMailConfig();

        try {
            Mail::raw(
                'This is a test email from '.SiteSettings::name().'. Your SMTP settings work.',
                fn ($m) => $m->to($to)->subject('Test email — '.SiteSettings::name())
            );

            Notification::make()
                ->title('Test email sent')
                ->body("Sent to {$to}. Check the inbox (and spam).")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Could not send')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }

    public function mount(): void
    {
        $values = SiteSettings::all();
        // TagsInput needs an array, not the stored comma string.
        $values['scrape_times'] = SiteSettings::scrapeTimes();
        // Toggles need a real boolean, not the stored '1'/'0' string.
        $values['scrape_notify'] = SiteSettings::scrapeNotify();
        $values['search_indexing'] = SiteSettings::get('search_indexing') !== '0';
        $values['comments_enabled'] = SiteSettings::commentsEnabled();
        $values['cookie_banner'] = SiteSettings::get('cookie_banner') !== '0';
        // SMTP fields (password is never prefilled).
        $values = array_merge($values, SiteSettings::mailSettings());

        $this->form->fill($values);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Settings')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->tabs([

                Forms\Components\Tabs\Tab::make('General')
                    ->icon('heroicon-o-globe-alt')
                    ->schema([

                Forms\Components\Section::make('Site identity')
                    ->description('Shown in the masthead, browser tab and search results.')
                    ->schema([
                        Forms\Components\TextInput::make('site_name')
                            ->label('Website name')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('contact_email')
                            ->label('Contact email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('site_logo')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('site')
                            ->imageEditor()
                            ->helperText('Shown in the masthead. Leave empty to show the website name as text.'),
                        Forms\Components\FileUpload::make('site_favicon')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('site')
                            ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'])
                            ->helperText('Browser tab icon. Square PNG, ICO or SVG — 32×32 or larger.'),
                        Forms\Components\ColorPicker::make('brand_color')
                            ->label('Brand colour')
                            ->rule('regex:/^#[0-9a-fA-F]{6}$/')
                            ->helperText('Accent colour across the public website — headlines, buttons, links, the breaking-news bar. Hover and tint shades are derived automatically.'),
                        Forms\Components\Textarea::make('site_description')
                            ->label('Default meta description (SEO)')
                            ->rows(2)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Footer')
                    ->schema([
                        Forms\Components\Textarea::make('site_tagline')
                            ->label('About text')
                            ->rows(2)
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('newsletter_text')
                            ->label('Newsletter text')
                            ->rows(2)
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('copyright_text')
                            ->label('Copyright line')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),

                    ]), // end General tab

                Forms\Components\Tabs\Tab::make('Content')
                    ->icon('heroicon-o-document-text')
                    ->schema([

                Forms\Components\Section::make('Comments')
                    ->schema([
                        Forms\Components\Toggle::make('comments_enabled')
                            ->label('Show the comment section on articles')
                            ->helperText('Turn off to hide comments (and stop new ones) across the whole site.'),
                    ]),

                Forms\Components\Section::make('Pages')
                    ->description('Impressum and Datenschutz are required for German sites. Leave a field empty and that page returns 404.')
                    ->schema([
                        Forms\Components\RichEditor::make('about_content')
                            ->label('Über uns (About us)')
                            ->helperText('Published at /ueber-uns and linked in the footer.')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('imprint_content')
                            ->label('Impressum')
                            ->helperText('Published at /impressum and linked in the footer.')
                            ->columnSpanFull(),
                        Forms\Components\RichEditor::make('privacy_content')
                            ->label('Datenschutzerklärung')
                            ->helperText('Published at /datenschutz and linked in the footer.')
                            ->columnSpanFull(),
                    ]),

                    ]), // end Content tab

                Forms\Components\Tabs\Tab::make('Scraping')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([

                Forms\Components\Section::make('Scraping schedule')
                    ->description('When the news scraper runs (German time). Needs the schedule:run cron active on the server.')
                    ->schema([
                        Forms\Components\Select::make('scrape_frequency')
                            ->label('Run')
                            ->options(SiteSettings::scrapeFrequencyOptions())
                            ->default('times')
                            ->required()
                            ->live(),
                        Forms\Components\Select::make('scrape_times')
                            ->label('Times')
                            ->multiple()
                            ->searchable()
                            ->options(SiteSettings::scrapeTimeOptions())
                            ->placeholder('Select hours…')
                            ->helperText('Pick the hours the scraper runs (24h, German time) — e.g. 06:00, 14:00, 23:00.')
                            ->live()
                            ->afterStateUpdated(function (Forms\Set $set, $state) {
                                // Keep the picked hours in chronological order.
                                if (is_array($state)) {
                                    $state = array_unique($state);
                                    sort($state);
                                    $set('scrape_times', $state);
                                }
                            })
                            ->visible(fn (Forms\Get $get) => $get('scrape_frequency') === 'times'),
                        Forms\Components\Toggle::make('scrape_notify')
                            ->label('Email me a summary after each run')
                            ->helperText('Sends a short report (new/updated articles, errors) to the contact email after every scrape. Turn off to stop the emails.')
                            ->columnSpanFull(),
                    ])->columns(2),

                    ]), // end Scraping tab

                Forms\Components\Tabs\Tab::make('Email')
                    ->icon('heroicon-o-envelope')
                    ->schema([

                Forms\Components\Section::make('Email (SMTP)')
                    ->description('Outgoing mail account for scrape reports and alerts. Leave empty to use the server default (.env). Save, then use “Send test email”.')
                    ->schema([
                        Forms\Components\Select::make('mail_mailer')
                            ->label('Mailer')
                            ->options([
                                'smtp'     => 'SMTP',
                                'sendmail' => 'Sendmail',
                                'log'      => 'Log (write to log file — no real send)',
                            ])
                            ->default('smtp')
                            ->live(),
                        Forms\Components\TextInput::make('mail_port')
                            ->label('Port')
                            ->numeric()
                            ->placeholder('587')
                            ->helperText('587 = STARTTLS · 465 = SSL')
                            ->maxLength(5)
                            ->visible(fn (Forms\Get $get) => $get('mail_mailer') === 'smtp'),
                        Forms\Components\TextInput::make('mail_host')
                            ->label('Host')
                            ->placeholder('w01861c2.kasserver.com')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('mail_mailer') === 'smtp'),
                        Forms\Components\TextInput::make('mail_username')
                            ->label('Username')
                            ->placeholder('dev@listandsell.de')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('mail_mailer') === 'smtp'),
                        Forms\Components\TextInput::make('mail_password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder('Leave blank to keep the current password')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('mail_mailer') === 'smtp'),
                        Forms\Components\TextInput::make('mail_local_domain')
                            ->label('Local domain')
                            ->placeholder('localhost')
                            ->helperText('EHLO name — usually "localhost" or your domain.')
                            ->maxLength(255)
                            ->visible(fn (Forms\Get $get) => $get('mail_mailer') === 'smtp'),
                        Forms\Components\TextInput::make('mail_from_name')
                            ->label('Sender name')
                            ->placeholder(fn () => SiteSettings::name())
                            ->maxLength(255),
                        Forms\Components\TextInput::make('mail_from_address')
                            ->label('Sender email')
                            ->email()
                            ->placeholder('dev@listandsell.de')
                            ->maxLength(255),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('sendTestEmail')
                                ->label('Send test mail')
                                ->icon('heroicon-o-paper-airplane')
                                ->modalDescription('Sends a test message using the saved SMTP settings. Save your changes first, then test.')
                                ->modalSubmitActionLabel('Send')
                                ->form([
                                    Forms\Components\TextInput::make('test_to')
                                        ->label('Send to')
                                        ->email()
                                        ->required()
                                        ->default(fn () => SiteSettings::notifyRecipient() ?: 'pankajlistandsell@gmail.com'),
                                ])
                                ->action(fn (array $data) => $this->sendTestEmail($data['test_to'])),
                        ])->columnSpanFull(),
                    ])->columns(2),

                    ]), // end Email tab

                Forms\Components\Tabs\Tab::make('SEO & Privacy')
                    ->icon('heroicon-o-magnifying-glass')
                    ->schema([

                Forms\Components\Section::make('Tracking & verification')
                    ->description('Scripts are added to the public website only — never to this admin panel.')
                    ->schema([
                        Forms\Components\TextInput::make('gtm_id')
                            ->label('Google Tag Manager ID')
                            ->placeholder('GTM-XXXXXXX')
                            ->maxLength(40)
                            ->rule('regex:/^$|^GTM-[A-Z0-9]+$/i')
                            ->helperText('Container ID from tagmanager.google.com. Loads only after cookie consent.'),
                        Forms\Components\TextInput::make('google_site_verification')
                            ->label('Google Search Console code')
                            ->maxLength(255)
                            ->helperText('Only the content value of the verification meta tag.'),
                        Forms\Components\Toggle::make('search_indexing')
                            ->label('Allow search engines to index this site')
                            ->helperText('Turn off while the site is in testing — adds a "noindex, nofollow" tag so Google stays away. Turn on before going live.')
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('cookie_banner')
                            ->label('Show GDPR cookie consent banner')
                            ->helperText('Required in Germany when tracking is active — Google Tag Manager then loads only after the visitor accepts analytics cookies.')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Spam protection (reCAPTCHA)')
                    ->description('Google reCAPTCHA v2 on the contact and comment forms. Get the keys at google.com/recaptcha — leave empty to turn it off.')
                    ->schema([
                        Forms\Components\TextInput::make('recaptcha_site_key')
                            ->label('Site key')
                            ->maxLength(255)
                            ->helperText('Public key — rendered in the form.'),
                        Forms\Components\TextInput::make('recaptcha_secret_key')
                            ->label('Secret key')
                            ->password()
                            ->revealable()
                            ->autocomplete('new-password')
                            ->placeholder('Leave blank to keep the current key')
                            ->maxLength(255)
                            ->helperText('Stored encrypted. Both keys are needed before the captcha appears.'),
                    ])->columns(2),

                    ]), // end SEO & Privacy tab

                Forms\Components\Tabs\Tab::make('Social')
                    ->icon('heroicon-o-share')
                    ->schema([

                Forms\Components\Section::make('Social links')
                    ->description('Leave empty to hide a link.')
                    ->schema([
                        Forms\Components\TextInput::make('social_facebook')->label('Facebook')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_twitter')->label('X (Twitter)')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_instagram')->label('Instagram')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_youtube')->label('YouTube')->url()->maxLength(255),
                    ])->columns(2),

                    ]), // end Social tab

                    ]), // end Tabs
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            if (in_array($key, ['mail_password', 'recaptcha_secret_key'], true)) {
                // Blank means "keep current". Otherwise store encrypted.
                if (filled($value)) {
                    Setting::set($key, Crypt::encryptString($value));
                }

                continue;
            }

            if (is_bool($value)) {
                // Toggles: store an explicit '1'/'0' so "off" persists
                // instead of collapsing to '' and falling back to the default.
                $value = $value ? '1' : '0';
            } elseif (is_array($value)) {
                if ($key === 'scrape_times') {
                    // Store the times de-duplicated and in chronological order.
                    $value = array_unique($value);
                    sort($value);
                    $value = implode(',', $value);
                } else {
                    // Uploads are a single path.
                    $value = reset($value) ?: '';
                }
            }

            Setting::set($key, (string) $value);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
