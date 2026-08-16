<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumns('plugins', [
            'slug',
            'repository_url',
            'manifest_data',
            'status',
        ]));
        $this->assertTrue(Schema::hasTable('categories'));
        $this->assertTrue(Schema::hasTable('tags'));
        $this->assertTrue(Schema::hasTable('plugin_submissions'));
    }

    public function test_homepage_is_available(): void
    {
        $this->get('/')->assertOk();
    }
}
