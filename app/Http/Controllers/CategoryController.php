<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Plugins\PluginDirectory;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(Category $category, PluginDirectory $directory): View
    {
        return view('categories.show', [
            'category' => $category,
            'plugins' => $category->plugins()
                ->published()
                ->with(['categories', 'tags'])
                ->orderBy('name')
                ->paginate(PluginDirectory::PER_PAGE),
        ]);
    }
}
