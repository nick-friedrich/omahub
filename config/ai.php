<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI review provider
    |--------------------------------------------------------------------------
    |
    | Used by the optional AI review stage, which runs after the deterministic
    | scan. The provider is configurable; OpenRouter is the default.
    |
    */

    'provider' => env('AI_PROVIDER', 'openrouter'),
    'base_url' => env('AI_BASE_URL', 'https://openrouter.ai/api/v1'),
    'key' => env('AI_API_KEY'),
    'model' => env('AI_MODEL', 'deepseek/deepseek-v4-flash'),

];
