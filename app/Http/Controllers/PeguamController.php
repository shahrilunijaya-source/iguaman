<?php

namespace App\Http\Controllers;

use App\Models\Form;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// External lawyer (peguam) area — cases assigned to the signed-in panel lawyer.
class PeguamController extends Controller
{
    public function dashboard(): View
    {
        $user = Auth::user();

        // Lawyer login links to peguam_panel via id_peguam_panel (IC). Match assigned cases by lawyer name/IC.
        $profile = $user->lawyerProfile;

        $kesSaya = $profile
            ? Form::where('nama_pegawai_yang_dapat_kes', 'like', '%'.$profile->nama_peguam.'%')->count()
            : 0;

        $stats = [
            'kes_saya' => $kesSaya,
            'nama' => $profile->nama_peguam ?? $user->name,
        ];

        return view('peguam.dashboard', compact('stats'));
    }
}
