<?php

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportPluginTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_reports_an_invalid_repository_url(): void
    {
        $this->artisan('plugins:import', ['url' => 'https://example.com/plugin'])
            ->expectsOutputToContain('Enter a public GitHub repository URL')
            ->assertFailed();
    }
}
