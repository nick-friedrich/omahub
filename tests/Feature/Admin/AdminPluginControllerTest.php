<?php

namespace Tests\Feature\Admin;

use App\Enums\PluginStatus;
use App\Enums\SecurityScanStatus;
use App\Models\Category;
use App\Models\Plugin;
use App\Models\SecurityScan;
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

    public function test_index_search_matches_name_owner_and_repository(): void
    {
        $lunar = Plugin::factory()->create([
            'name' => 'Lunar Wobble',
            'repository_owner' => 'moon-unit',
            'repository_name' => 'lunar-wobble',
        ]);
        $nova = Plugin::factory()->create([
            'name' => 'Nova Flare',
            'repository_owner' => 'star-fleet',
            'repository_name' => 'nova-flare',
        ]);

        $this->get(route('admin.plugins.index', ['q' => 'moon-unit']))
            ->assertOk()
            ->assertSee($lunar->name)
            ->assertDontSee($nova->name);

        $this->get(route('admin.plugins.index', ['q' => 'nova']))
            ->assertOk()
            ->assertSee($nova->name)
            ->assertDontSee($lunar->name);
    }

    public function test_index_shows_latest_scan_risk_level_in_table(): void
    {
        $risky = Plugin::factory()->create(['name' => 'Zulu Risky Plugin', 'repository_name' => 'risky-repo']);
        $this->createSucceededScan($risky, 'high');

        $clear = Plugin::factory()->create(['name' => 'Alpha Safe Plugin', 'repository_name' => 'safe-repo']);
        $this->createSucceededScan($clear, 'none');

        $unscanned = Plugin::factory()->create(['name' => 'Beta Unscanned Plugin', 'repository_name' => 'unscanned-repo']);

        $this->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSeeInOrder([$risky->name, 'risky-repo', 'High'])
            ->assertSeeInOrder([$clear->name, 'safe-repo', 'None'])
            ->assertSeeInOrder([$unscanned->name, 'unscanned-repo', 'Not scanned']);
    }

    public function test_index_uses_the_latest_scan_for_risk(): void
    {
        $plugin = Plugin::factory()->create(['name' => 'Evolving Beast']);

        $this->createSucceededScan($plugin, 'low', commit: 'aaaa1111');
        $this->createSucceededScan($plugin, 'critical', commit: 'bbbb2222');

        $this->get(route('admin.plugins.index'))
            ->assertOk()
            ->assertSeeInOrder([$plugin->name, 'Critical']);
    }

    public function test_index_filters_plugins_by_latest_scan_risk(): void
    {
        $high = Plugin::factory()->create(['name' => 'High Voltage']);
        $this->createSucceededScan($high, 'high');

        $clear = Plugin::factory()->create(['name' => 'Clear Water']);
        $this->createSucceededScan($clear, 'none');

        $unscanned = Plugin::factory()->create(['name' => 'Untouched Plane']);

        $this->get(route('admin.plugins.index', ['risk' => 'high']))
            ->assertOk()
            ->assertSee($high->name)
            ->assertDontSee($clear->name)
            ->assertDontSee($unscanned->name);

        $this->get(route('admin.plugins.index', ['risk' => 'unscanned']))
            ->assertOk()
            ->assertSee($unscanned->name)
            ->assertDontSee($high->name)
            ->assertDontSee($clear->name);
    }

    private function createSucceededScan(Plugin $plugin, string $riskLevel, string $commit = 'deadbeef'): SecurityScan
    {
        return SecurityScan::query()->create([
            'plugin_id' => $plugin->id,
            'commit_sha' => $commit,
            'status' => SecurityScanStatus::Succeeded,
            'risk_level' => $riskLevel,
            'rules_run' => ['sudo'],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }
}
