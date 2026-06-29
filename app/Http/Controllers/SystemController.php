<?php

namespace App\Http\Controllers;

use App\Models\ButiranPeguamPanel2;
use App\Models\Form;
use App\Models\PeguamPanel;
use App\Models\User;
use Illuminate\View\View;

// Staff dashboard (admin / pengarah / koordinator / pegawai) — rekod-kes + panel admin.
class SystemController extends Controller
{
    public function utama(): View
    {
        $stats = [
            'kes' => Form::count(),
            'kes_tutup' => Form::whereNotNull('tarikh_tutup_fail')->count(),
            'peguam' => PeguamPanel::count(),
            'mohon_peguam' => ButiranPeguamPanel2::where('permohonan_status', '0')->count(),
            'pengguna' => User::where('user_type', User::TYPE_STAFF)->count(),
        ];

        return view('system.utama', compact('stats'));
    }
}
