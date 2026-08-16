<?php

namespace App\Http\Controllers;

use App\Services\Plugins\PluginDirectory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function index(Request $request, PluginDirectory $directory): View
    {
        $query = (string) $request->query('q', '');

        return view('search', [
            'query' => $query,
            'plugins' => $directory->search($query),
        ]);
    }
}
