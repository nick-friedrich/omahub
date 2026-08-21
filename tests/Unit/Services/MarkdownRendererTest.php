<?php

namespace Tests\Unit\Services;

use App\Services\Markdown\MarkdownRenderer;
use PHPUnit\Framework\TestCase;

class MarkdownRendererTest extends TestCase
{
    private MarkdownRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new MarkdownRenderer;
    }

    public function test_it_renders_gfm_tables(): void
    {
        $html = $this->renderer->render("| Option | Description |\n| --- | --- |\n| `iconSize` | Base size |");

        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<th>Option</th>', $html);
        $this->assertStringContainsString('<code>iconSize</code>', $html);
    }

    public function test_it_rewrites_relative_images_to_raw_content_urls(): void
    {
        $html = $this->renderer->render(
            "![preview](preview.png)\n\n![docs](docs/screenshot.png)",
            'https://raw.githubusercontent.com/nick-friedrich/hyprland-dock/master',
        );

        $this->assertStringContainsString(
            'src="https://raw.githubusercontent.com/nick-friedrich/hyprland-dock/master/preview.png"',
            $html,
        );
        $this->assertStringContainsString(
            'src="https://raw.githubusercontent.com/nick-friedrich/hyprland-dock/master/docs/screenshot.png"',
            $html,
        );
    }

    public function test_it_leaves_absolute_images_and_links_untouched(): void
    {
        $html = $this->renderer->render(
            "![badge](https://img.shields.io/static/v1.svg)\n\n[site](https://example.com)",
            'https://raw.githubusercontent.com/nick-friedrich/hyprland-dock/master',
        );

        $this->assertStringContainsString('src="https://img.shields.io/static/v1.svg"', $html);
        $this->assertStringNotContainsString('img.shields.io/static/v1.svg/external', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
    }

    public function test_it_keeps_relative_images_when_no_base_url_is_given(): void
    {
        $html = $this->renderer->render('![preview](preview.png)');

        $this->assertStringContainsString('src="preview.png"', $html);
    }

    public function test_it_extracts_the_first_readme_image(): void
    {
        $url = $this->renderer->firstImageUrl(
            "`![not an image](ignored.png)`\n\n![preview][image]\n\n[image]: docs/preview.png",
            'https://raw.githubusercontent.com/omarchy/example/main',
        );

        $this->assertSame('https://raw.githubusercontent.com/omarchy/example/main/docs/preview.png', $url);
    }

    public function test_it_only_extracts_web_images(): void
    {
        $this->assertNull($this->renderer->firstImageUrl('![local](preview.png)'));
        $this->assertNull($this->renderer->firstImageUrl('![unsafe](data:image/png;base64,abc)'));
    }

    public function test_it_escapes_raw_html_instead_of_rendering_it(): void
    {
        $html = $this->renderer->render("<script>alert('xss')</script>\n\n**bold**");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    public function test_it_renders_strikethrough_and_task_list_from_gfm(): void
    {
        $html = $this->renderer->render("- [x] done\n- [ ] todo\n\n~~removed~~");

        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('<del>removed</del>', $html);
    }
}
