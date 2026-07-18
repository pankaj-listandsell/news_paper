<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\SiteSettings;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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

    public function mount(): void
    {
        $this->form->fill(SiteSettings::all());
    }

    public function form(Form $form): Form
    {
        return $form
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

                Forms\Components\Section::make('Tracking & verification')
                    ->description('Scripts are added to the public website only — never to this admin panel.')
                    ->schema([
                        Forms\Components\TextInput::make('google_analytics_id')
                            ->label('Google Analytics ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->maxLength(40)
                            ->rule('regex:/^$|^(G-[A-Z0-9]+|UA-[0-9]+-[0-9]+|GTM-[A-Z0-9]+)$/i')
                            ->helperText('GA4 measurement ID from analytics.google.com. Leave empty to disable tracking.'),
                        Forms\Components\TextInput::make('google_site_verification')
                            ->label('Google Search Console code')
                            ->maxLength(255)
                            ->helperText('Only the content value of the verification meta tag.'),
                    ])->columns(2),

                Forms\Components\Section::make('Pages')
                    ->description('Impressum and Datenschutz are required for German sites. Leave a field empty and that page returns 404.')
                    ->collapsed()
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

                Forms\Components\Section::make('Social links')
                    ->description('Leave empty to hide a link.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('social_facebook')->label('Facebook')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_twitter')->label('X (Twitter)')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_instagram')->label('Instagram')->url()->maxLength(255),
                        Forms\Components\TextInput::make('social_youtube')->label('YouTube')->url()->maxLength(255),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, is_array($value) ? (reset($value) ?: '') : (string) $value);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
