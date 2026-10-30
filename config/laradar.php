<?php

// Auto-detect AI provider from whichever API key is present in .env.
// Explicit AI_PROVIDER always takes priority over auto-detection.
// Priority order when multiple keys exist: anthropic → openai → groq → mistral → openrouter → gemini → ollama
$_aiProvider = env('AI_PROVIDER')
    ?? (env('ANTHROPIC_API_KEY')  ? 'anthropic'  : null)
    ?? (env('OPENAI_API_KEY')     ? 'openai'     : null)
    ?? (env('GROQ_API_KEY')       ? 'groq'       : null)
    ?? (env('MISTRAL_API_KEY')    ? 'mistral'    : null)
    ?? (env('OPENROUTER_API_KEY') ? 'openrouter' : null)
    ?? (env('GEMINI_API_KEY')     ? 'gemini'     : null)
    ?? (env('OLLAMA_MODEL')       ? 'ollama'     : null)
    ?? null; // null = no key found, AI will be disabled even if AI_ENABLED=true

return [

    /*
    |--------------------------------------------------------------------------
    | Interactive Dashboard
    |--------------------------------------------------------------------------
    | Accessible at /{path} — only registered when APP_ENV=local by default.
    */
    'dashboard' => [
        'enabled'    => true,
        'path'       => 'laradar',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | What to scan
    |--------------------------------------------------------------------------
    */
    'scan' => [
        'models'       => true,
        'controllers'  => true,
        'routes'       => true,
        'migrations'   => true,
        'jobs'         => true,
        'events'       => true,
        'services'     => true,
        'repositories' => true,
        'observers'    => true,
        'policies'     => true,
        'modules'      => true,
        'packages'     => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom scan paths
    |--------------------------------------------------------------------------
    | Set to null to auto-detect.
    */
    'paths' => [
        'models'       => null,
        'controllers'  => null,
        'routes'       => null,
        'migrations'   => null,
        'jobs'         => null,
        'events'       => null,
        'services'     => null,
        'repositories' => null,
        'observers'    => null,
        'policies'     => null,
        'modules'      => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | App namespace
    |--------------------------------------------------------------------------
    */
    'app_namespace' => null,

    /*
    |--------------------------------------------------------------------------
    | Include vendor / framework routes
    |--------------------------------------------------------------------------
    */
    'include_vendor_routes' => false,

    /*
    |--------------------------------------------------------------------------
    | Default output formats (artisan command)
    |--------------------------------------------------------------------------
    */
    'output' => ['json', 'html', 'markdown'],

    /*
    |--------------------------------------------------------------------------
    | AI Analysis
    |--------------------------------------------------------------------------
    | Minimum setup — just two lines in .env:
    |
    |   AI_ENABLED=true
    |   ANTHROPIC_API_KEY=sk-ant-...   ← or any supported key below
    |
    | The provider is auto-detected from whichever key is present in .env.
    | No need to set AI_PROVIDER — it is resolved automatically.
    | Set AI_PROVIDER only if you want to force a specific provider.
    |
    | Supported keys (add whichever you have):
    |
    |   GEMINI_API_KEY      + GEMINI_MODEL      → gemini
    |   OPENAI_API_KEY      + OPENAI_MODEL      → openai
    |   ANTHROPIC_API_KEY   + ANTHROPIC_MODEL   → anthropic
    |   MISTRAL_API_KEY     + MISTRAL_MODEL     → mistral
    |   GROQ_API_KEY        + GROQ_MODEL        → groq
    |   OPENROUTER_API_KEY  + OPENROUTER_MODEL  → openrouter
    |   OLLAMA_MODEL        + OLLAMA_BASE_URL   → ollama (no key needed)
    |
    | Recommended models per provider:
    |   gemini      → gemini-2.5-flash
    |   openai      → gpt-4o
    |   anthropic   → claude-sonnet-4-6
    |   mistral     → mistral-large-latest
    |   groq        → llama-3.3-70b-versatile
    |   ollama      → llama3.2
    |   openrouter  → google/gemini-2.5-flash  (free — see openrouter.ai/models)
    */
    'ai' => [
        'enabled'     => (bool) env('AI_ENABLED', false),
        'provider'    => $_aiProvider,
        'temperature' => (float) env('AI_TEMPERATURE', 0.2),

        // Resolved from the auto-detected (or explicit) provider — no hardcoded default.
        'api_key' => match ($_aiProvider) {
            'openai'     => env('OPENAI_API_KEY'),
            'anthropic'  => env('ANTHROPIC_API_KEY'),
            'mistral'    => env('MISTRAL_API_KEY'),
            'groq'       => env('GROQ_API_KEY'),
            'ollama'     => null,
            'openrouter' => env('OPENROUTER_API_KEY'),
            'gemini'     => env('GEMINI_API_KEY'),
            default      => null,
        },

        'model' => match ($_aiProvider) {
            'openai'     => env('OPENAI_MODEL'),
            'anthropic'  => env('ANTHROPIC_MODEL'),
            'mistral'    => env('MISTRAL_MODEL'),
            'groq'       => env('GROQ_MODEL'),
            'ollama'     => env('OLLAMA_MODEL'),
            'openrouter' => env('OPENROUTER_MODEL'),
            'gemini'     => env('GEMINI_MODEL'),
            default      => null,
        },

        // Ollama only — base URL of the running Ollama server
        'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),

        // Max characters allowed per chat message
        'max_message_length' => (int) env('AI_MAX_MESSAGE_LENGTH', 5000),

        // Requests per minute allowed for chat/analyze endpoints (0 = no limit)
        'rate_limit' => (int) env('AI_RATE_LIMIT', 30),

        // Fallback provider if the primary fails (null = no fallback)
        'fallback_provider' => env('AI_FALLBACK_PROVIDER'),
    ],

];
