<?php

namespace Tests\Feature\Models;

use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\PluginSubmission;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PluginTest extends TestCase
{
    use RefreshDatabase;

    public function test_plugin_attributes_are_cast_to_domain_types(): void
    {
        $plugin = Plugin::factory()->published()->create();

        $this->assertSame(PluginStatus::Published, $plugin->status);
        $this->assertIsArray($plugin->manifest_data);
        $this->assertIsBool($plugin->is_archived);
        $this->assertNotNull($plugin->published_at);
    }

    public function test_published_scope_excludes_non_public_plugins(): void
    {
        $published = Plugin::factory()->published()->create();
        Plugin::factory()->create();
        Plugin::factory()->archived()->create();

        $this->assertTrue(Plugin::query()->published()->get()->contains($published));
        $this->assertCount(1, Plugin::query()->published()->get());
    }

    public function test_plugin_has_categories_tags_and_submissions(): void
    {
        $plugin = Plugin::factory()->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $submission = PluginSubmission::factory()->for($plugin)->create();

        $plugin->categories()->attach($category);
        $plugin->tags()->attach($tag);

        $this->assertTrue($plugin->categories->contains($category));
        $this->assertTrue($plugin->tags->contains($tag));
        $this->assertTrue($plugin->submissions->contains($submission));
    }
}
