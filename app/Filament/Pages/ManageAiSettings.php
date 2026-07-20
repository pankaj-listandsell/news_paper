<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\AiConfig;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ManageAiSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'AI Settings';

    protected static ?string $title = 'AI Settings';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.manage-ai-settings';

    public ?array $data = [];

    /**
     * Which setting keys this page owns.
     *
     * @var list<string>
     */
    protected array $keys = [
        'ai_provider',
        'ai_language',
        'ai_claude_api_key',
        'ai_claude_model',
        'ai_openai_api_key',
        'ai_openai_model',
        'ai_openai_image_model',
        'ai_openai_image_quality',
    ];

    public function mount(): void
    {
        $values = [];
        foreach ($this->keys as $key) {
            $values[$key] = Setting::get($key);
        }

        // Sensible defaults from config when nothing saved yet.
        $values['ai_provider']      = $values['ai_provider']      ?: config('ai.default');
        $values['ai_language']      = $values['ai_language']      ?: config('ai.language');
        $values['ai_claude_model']  = $values['ai_claude_model']  ?: config('ai.providers.claude.model');
        $values['ai_openai_model']  = $values['ai_openai_model']  ?: config('ai.providers.openai.model');
        $values['ai_openai_image_model']   = $values['ai_openai_image_model']   ?: AiConfig::imageModel();
        $values['ai_openai_image_quality'] = $values['ai_openai_image_quality'] ?: AiConfig::imageQuality();

        $this->form->fill($values);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->description('Default provider and output language.')
                    ->schema([
                        Forms\Components\Select::make('ai_provider')
                            ->label('Default AI provider')
                            ->options(AiConfig::providerOptions())
                            ->required()
                            ->helperText('Used when a source has no provider selected.'),
                        Forms\Components\TextInput::make('ai_language')
                            ->label('Rewrite language')
                            ->placeholder('English / German / Hindi')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Claude (Anthropic)')
                    ->schema([
                        Forms\Components\TextInput::make('ai_claude_api_key')
                            ->label('Anthropic API key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk-ant-...')
                            ->helperText('Get it from console.anthropic.com.'),
                        Forms\Components\TextInput::make('ai_claude_model')
                            ->label('Model')
                            ->placeholder('claude-sonnet-5'),
                    ])->columns(2),

                Forms\Components\Section::make('OpenAI (ChatGPT)')
                    ->schema([
                        Forms\Components\TextInput::make('ai_openai_api_key')
                            ->label('OpenAI API key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk-...')
                            ->helperText('Get it from platform.openai.com.'),
                        Forms\Components\TextInput::make('ai_openai_model')
                            ->label('Text model')
                            ->placeholder('gpt-4o-mini')
                            ->helperText('Used for rewriting title/description/body.'),
                        Forms\Components\Select::make('ai_openai_image_model')
                            ->label('Image model')
                            ->options(AiConfig::imageModelOptions())
                            ->native(false)
                            ->helperText('Used for AI image generation.'),
                        Forms\Components\Select::make('ai_openai_image_quality')
                            ->label('Image quality')
                            ->options([
                                'low'    => 'Low (cheapest)',
                                'medium' => 'Medium',
                                'high'   => 'High (most expensive)',
                            ])
                            ->native(false)
                            ->helperText('Higher quality costs more per image.'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('AI settings saved')
            ->success()
            ->send();
    }
}
