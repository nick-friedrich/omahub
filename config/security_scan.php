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
    'sandbox_image' => env('SCAN_SANDBOX_IMAGE'),
    'sandbox_repo_path' => '/srv/omahub',
    // Host-side path of this repo when the app runs inside a container and talks to
    // the host Docker daemon (Docker-out-of-Docker). The sandbox -v source is resolved
    // by the host daemon, so it must be a host path. Leave unset when PHP runs on the
    // host (local dev), where base_path() is already a host path.
    'sandbox_host_repo_path' => env('SCAN_SANDBOX_HOST_REPO_PATH'),
    'max_file_size' => (int) env('SCAN_MAX_FILE_SIZE', 2 * 1024 * 1024),
    'max_files' => (int) env('SCAN_MAX_FILES', 2000),

];
