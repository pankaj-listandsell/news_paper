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

    /*
    |--------------------------------------------------------------------------
    | Pricing (USD per 1M tokens)
    |--------------------------------------------------------------------------
    | Used to cost each API call for the dashboard tracker. Update these when
    | the providers change their prices. Unknown models cost 0.
    */
    'rates' => [
        // OpenAI — text
        'gpt-4o-mini'     => ['input' => 0.15,  'output' => 0.60],
        'gpt-4o'          => ['input' => 2.50,  'output' => 10.00],
        'gpt-5.4-nano'    => ['input' => 0.20,  'output' => 1.25],
        'gpt-5.4-mini'    => ['input' => 0.75,  'output' => 4.50],
        'gpt-5.4'         => ['input' => 2.50,  'output' => 15.00],
        'gpt-5.5'         => ['input' => 5.00,  'output' => 30.00],
        // OpenAI — image
        'gpt-image-1'      => ['input' => 5.00, 'output' => 40.00],
        'gpt-image-1-mini' => ['input' => 2.50, 'output' => 8.00],
        'gpt-image-1.5'    => ['input' => 8.00, 'output' => 32.00],
        'gpt-image-2'      => ['input' => 8.00, 'output' => 30.00],
        // Anthropic
        'claude-sonnet-5' => ['input' => 3.00,  'output' => 15.00],
        'claude-opus-4-8' => ['input' => 5.00,  'output' => 25.00],
        'claude-haiku-4-5' => ['input' => 1.00, 'output' => 5.00],
    ],

];
