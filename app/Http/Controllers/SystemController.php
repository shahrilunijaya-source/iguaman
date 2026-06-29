<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// Authenticated dashboard. Stub — build out per project.
class SystemController extends Controller
{
    public function utama(): View
    {
        return view('system.utama');
    }
}
