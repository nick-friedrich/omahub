<?php

namespace Tests\Feature\Admin;

use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\FakesGitHub;
use Tests\TestCase;

class AdminPluginControllerTest extends TestCase
{
    use FakesGitHub;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_updating_plugin_metadata_and_relations(): void
    {
        $plugin = Plugin::factory()->create();
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();
        $otherTag = Tag::factory()->create();

        $this->put(route('admin.plugins.update', $plugin), [
            'name' => 'Renamed Plugin',
            'description' => 'A fresh description.',
            'author_name' => 'Jane Doe',
            'author_url' => 'https://github.com/jane',
            'license' => 'MIT',
            'homepage_url' => 'https://example.com',
            'categories' => [$category->id],
            'tags' => [$tag->id],
        ])->assertRedirect(route('admin.plugins.index'));

        $plugin->refresh();

        $this->assertSame('Renamed Plugin', $plugin->name);
        $this->assertSame('A fresh description.', $plugin->description);
        $this->assertSame('Jane Doe', $plugin->author_name);
        $this->assertTrue($plugin->categories->pluck('id')->contains($category->id));
        $this->assertTrue($plugin->tags->pluck('id')->contains($tag->id));
        $this->assertNotContains($otherTag->id, $plugin->tags->pluck('id')->all());
    }

    public function test_update_validation_requires_name_and_valid_urls(): void
    {
        $plugin = Plugin::factory()->create();

        $this->put(route('admin.plugins.update', $plugin), [
            'name' => '',
            'author_url' => 'not-a-url',
        ])->assertSessionHasErrors(['name', 'author_url']);
    }

    public function test_status_change_publishes_a_plugin(): void
    {
        $plugin = Plugin::factory()->create([
            'status' => PluginStatus::Pending,
            'published_at' => null,
        ]);

        $this->post(route('admin.plugins.status', $plugin), [
            'status' => 'published',
        ])->assertRedirect();

        $plugin->refresh();
        $this->assertSame(PluginStatus::Published, $plugin->status);
        $this->assertNotNull($plugin->published_at);
    }

    public function test_refresh_reimports_a_plugin_from_github(): void
    {
        $this->fakeGitHub();

        $plugin = Plugin::factory()->create([
            'repository_owner' => 'acme',
            'repository_name' => 'workspace-switcher',
            'name' => 'Stale',
            'readme_markdown' => null,
        ]);

        $this->post(route('admin.plugins.refresh', $plugin))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame('Workspace Switcher', $plugin->fresh()->name);
        $this->assertStringContainsString('Workspace Switcher', (string) $plugin->fresh()->readme_markdown);
    }

    public function test_delete_removes_the_plugin(): void
    {
        $plugin = Plugin::factory()->create();

        $this->delete(route('admin.plugins.destroy', $plugin))
            ->assertRedirect(route('admin.plugins.index'));

        $this->assertDatabaseMissing('plugins', ['id' => $plugin->id]);
    }
}
