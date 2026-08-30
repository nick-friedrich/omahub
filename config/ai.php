<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI review provider
    |--------------------------------------------------------------------------
    |
    | Used by the AI review stage, which runs after the deterministic scan and
    | gives an advisory risk assessment on top of it. The provider is
    | configurable; OpenRouter is the default. AI results are advisory only and
    | never replace or hide the deterministic findings.
    |
    */

    'provider' => env('AI_PROVIDER', 'openrouter'),
    'base_url' => env('AI_BASE_URL', 'https://openrouter.ai/api/v1'),
    'key' => env('AI_API_KEY'),
    // Leading ~ is required: OpenRouter uses "~name-latest" for auto-updating
    // alias ids (e.g. ~deepseek/deepseek-v4-flash-latest); without it the id
    // does not exist and the API answers 400.
    'model' => env('AI_MODEL', '~deepseek/deepseek-v4-flash-latest'),
    'timeout' => (int) env('AI_TIMEOUT', 90),

    // Bounds for the repository content sample sent to the model. The whole
    // tarball is too large for a prompt, so only this many files (preferring
    // manifest, README, and script/config files) are included, each truncated.
    'max_sample_files' => (int) env('AI_MAX_SAMPLE_FILES', 40),
    'max_sample_lines' => (int) env('AI_MAX_SAMPLE_LINES', 200),

    // Cap on the README text sent to the model.
    'max_readme_chars' => (int) env('AI_MAX_README_CHARS', 8000),

];
