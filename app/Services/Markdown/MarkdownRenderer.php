<?php

namespace App\Services\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private readonly MarkdownConverter $converter;

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

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
