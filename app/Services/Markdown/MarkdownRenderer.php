<?php

namespace App\Services\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private readonly MarkdownConverter $converter;

    private ?string $imageBaseUrl = null;

    private ?string $firstImageUrl = null;

    private bool $extractFirstImage = false;

    public function __construct()
    {
        // Raw HTML in untrusted READMEs is escaped rather than rendered,
        // and dangerous link schemes (javascript:, etc.) are removed.
        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);
        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);

        // Rewrite relative image sources to the repository's raw content URLs
        // so screenshots referenced like ![](preview.png) resolve correctly,
        // and rewrite relative links (e.g. [LICENSE](LICENSE)) the same way so
        // they point at the GitHub raw file rather than a site path that 404s.
        $environment->addEventListener(DocumentParsedEvent::class, function (DocumentParsedEvent $event): void {
            foreach ($event->getDocument()->iterator() as $node) {
                if ($node instanceof Image) {
                    $url = $node->getUrl();

                    if ($this->isLocalPath($url) && $this->imageBaseUrl !== null) {
                        $node->setUrl($this->resolveContentUrl($url));
                    }

                    if ($this->extractFirstImage && $this->firstImageUrl === null && $this->isWebUrl($node->getUrl())) {
                        $this->firstImageUrl = $node->getUrl();
                    }

                    continue;
                }

                if ($node instanceof Link && $this->imageBaseUrl !== null) {
                    $url = $node->getUrl();

                    if ($this->isLocalPath($url) && ! $this->isPageAnchor($url)) {
                        $node->setUrl($this->resolveContentUrl($url));
                    }
                }
            }
        });

        $this->converter = new MarkdownConverter($environment);
    }

    /**
     * @param  string|null  $imageBaseUrl  e.g. the raw.githubusercontent.com base
     *                                     directory for the plugin's repository
     */
    public function render(string $markdown, ?string $imageBaseUrl = null): string
    {
        $this->imageBaseUrl = $imageBaseUrl;

        try {
            return $this->converter->convert($markdown)->getContent();
        } finally {
            $this->imageBaseUrl = null;
        }
    }

    public function firstImageUrl(string $markdown, ?string $imageBaseUrl = null): ?string
    {
        $this->imageBaseUrl = $imageBaseUrl;
        $this->extractFirstImage = true;
        $this->firstImageUrl = null;

        try {
            $this->converter->convert($markdown);

            return $this->firstImageUrl;
        } finally {
            $this->imageBaseUrl = null;
            $this->extractFirstImage = false;
            $this->firstImageUrl = null;
        }
    }

    private function isLocalPath(string $url): bool
    {
        // Absolute URLs, protocol-relative URLs, data URIs and root paths stay as-is.
        return ! preg_match('/^(?:[a-z][a-z0-9+.-]*:)?\/\/|^(?:data:|\/)/i', $url);
    }

    private function isWebUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//i', $url) === 1;
    }

    private function isPageAnchor(string $url): bool
    {
        return str_starts_with($url, '#');
    }

    private function resolveContentUrl(string $url): string
    {
        $path = trim(str_replace('\\', '/', $url), './'); // strip leading ./ or /
        $segments = array_map('rawurlencode', array_filter(explode('/', $path), static fn (string $s): bool => $s !== ''));

        return rtrim($this->imageBaseUrl ?? '', '/').'/'.implode('/', $segments);
    }
}
