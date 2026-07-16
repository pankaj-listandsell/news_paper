<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default AI provider
    |--------------------------------------------------------------------------
    | Used when a news source doesn't specify its own provider.
    | Supported: "claude", "openai"
    */
    'default' => env('AI_PROVIDER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [

        'claude' => [
            'label'       => 'Claude (Anthropic)',
            'api_key'     => env('ANTHROPIC_API_KEY'),
            'model'       => env('CLAUDE_MODEL', 'claude-sonnet-5'),
            'api_url'     => 'https://api.anthropic.com/v1/messages',
            'api_version' => '2023-06-01',
        ],

        'openai' => [
            'label'       => 'OpenAI (ChatGPT)',
            'api_key'     => env('OPENAI_API_KEY'),
            'model'       => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'api_url'     => 'https://api.openai.com/v1/chat/completions',
            'image_url'     => 'https://api.openai.com/v1/images/generations',
            'image_model'   => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
            'image_size'    => env('OPENAI_IMAGE_SIZE', '1024x1024'),
            'image_quality' => env('OPENAI_IMAGE_QUALITY', 'low'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Rewrite language
    |--------------------------------------------------------------------------
    | Output language for the rewritten title/description.
    | e.g. "German", "Hindi", "English". Keep same as source to only rewrite.
    */
    'language' => env('AI_REWRITE_LANGUAGE', 'German'),

];
