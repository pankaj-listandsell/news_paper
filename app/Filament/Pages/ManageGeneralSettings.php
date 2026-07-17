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
                            ->helperText('Leave empty to show the website name as text instead.')
                            ->columnSpanFull(),
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
