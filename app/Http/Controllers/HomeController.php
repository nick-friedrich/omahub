<?php

namespace App\Http\Controllers;

use App\Services\Plugins\PluginDirectory;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(PluginDirectory $directory): View
    {
        return view('home', [
            'recentlyUpdated' => $directory->recentlyUpdated(),
            'newest' => $directory->newest(),
            'popular' => $directory->popular(),
        ]);
    }
}
