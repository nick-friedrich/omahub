<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ResourcesController extends Controller
{
    public function __invoke(): View
    {
        return view('resources', [
            'resources' => [
                [
                    'title' => 'Omarchy',
                    'url' => 'https://omarchy.org',
                    'label' => 'Official site',
                    'description' => 'The official Omarchy Linux distribution by DHH — beautiful, modern &amp; opinionated.',
                ],
                [
                    'title' => 'Omarchy Themes',
                    'url' => 'https://bjarneo.github.io/omarchy-themes/',
                    'label' => 'Community gallery',
                    'description' => '3,000+ curated wallpapers, each with five Omarchy theme variants — one-click apply with Aether.',
                ],
            ],
        ]);
    }
}
