<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SubmitController extends Controller
{
    public function __invoke(): View
    {
        return view('submit');
    }
}
