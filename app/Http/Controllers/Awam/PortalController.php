<?php

namespace App\Http\Controllers\Awam;

use App\Http\Controllers\Controller;
use App\Models\KhidmatNasihat;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function index(): View
    {
        $khidmat = KhidmatNasihat::query()
            ->where('id_pengguna', auth()->id())
            ->with(['cawangan', 'temuJanji'])
            ->orderByDesc('id')
            ->paginate(10);

        return view('awam.dashboard', ['khidmat' => $khidmat]);
    }
}
