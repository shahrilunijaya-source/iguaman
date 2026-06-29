<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\PeguamPanel;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

// External lawyer (peguam) area — cases assigned to the signed-in panel lawyer + profile.
class PeguamController extends Controller
{
    public function dashboard(): View
    {
        $profile = $this->profile();

        $stats = [
            'kes_saya' => $profile ? $this->kesQuery($profile->nama_peguam)->count() : 0,
            'nama' => $profile->nama_peguam ?? Auth::user()->name,
        ];

        return view('peguam.dashboard', compact('stats'));
    }

    public function kes(): View
    {
        $profile = $this->profile();

        $kes = $profile
            ? $this->kesQuery($profile->nama_peguam)->orderByDesc('id')->paginate(20)
            : Form::query()->whereRaw('1 = 0')->paginate(20);

        return view('peguam.kes', ['kes' => $kes, 'profile' => $profile]);
    }

    public function profil(): View
    {
        $profile = $this->profile();

        return view('peguam.profil', [
            'profile' => $profile,
            'user' => Auth::user(),
            'b' => $profile?->butiran,
        ]);
    }

    /** Panel-lawyer master record for the signed-in user (links via id_peguam_panel = kp_peguam). */
    private function profile(): ?PeguamPanel
    {
        return Auth::user()->lawyerProfile;
    }

    /** Cases assigned to a lawyer by name. */
    private function kesQuery(string $namaPeguam)
    {
        return Form::where('nama_pegawai_yang_dapat_kes', $namaPeguam);
    }
}
