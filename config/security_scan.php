<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deterministic security scan
    |--------------------------------------------------------------------------
    |
    | The scan downloads a plugin repository tarball at an exact commit and runs
    | static rules over its files. Untrusted content is processed inside a Docker
    | sandbox rather than on the host (SandboxRunner); set enabled=false to scan
    | on the host instead, which is useful for local development and tests.
    |
    */

    'enabled' => filter_var(env('SCAN_SANDBOX_ENABLED', env('APP_ENV') !== 'testing'), FILTER_VALIDATE_BOOL),
    'sandbox_image' => env('SCAN_SANDBOX_IMAGE', 'ghcr.io/omarchy/omahub-scan:sandbox'),
    'sandbox_repo_path' => '/srv/omahub',
    'max_file_size' => (int) env('SCAN_MAX_FILE_SIZE', 2 * 1024 * 1024),
    'max_files' => (int) env('SCAN_MAX_FILES', 2000),

];
