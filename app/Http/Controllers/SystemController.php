<?php

namespace App\Http\Controllers;

use App\Models\AuditTrail;
use App\Models\ButiranPeguamPanel2;
use App\Models\Form;
use App\Models\Oyd;
use App\Models\PeguamPanel;
use App\Models\User;
use Illuminate\View\View;

// Staff dashboard (admin / pengarah / koordinator / pegawai) — rekod-kes + panel admin command center.
class SystemController extends Controller
{
    public function utama(): View
    {
        $stats = [
            'kes' => Form::count(),
            'kes_aktif' => Form::whereNull('tarikh_tutup_fail')->count(),
            'kes_tutup' => Form::whereNotNull('tarikh_tutup_fail')->count(),
            'belum_agih' => Form::where(fn ($w) => $w->whereNull('nama_pegawai_yang_dapat_kes')->orWhere('nama_pegawai_yang_dapat_kes', ''))->count(),
            'peguam' => PeguamPanel::count(),
            'mohon_peguam' => ButiranPeguamPanel2::where('permohonan_status', '0')->count(),
            'oyd' => Oyd::count(),
            'pengguna' => User::where('user_type', User::TYPE_STAFF)->count(),
        ];

        $recentKes = Form::orderByDesc('id')->limit(6)->get(['id', 'nama', 'no_fail', 'status', 'kategori_kes', 'cawangan', 'tarikh_permohonan']);
        $recentAudit = AuditTrail::orderByDesc('id')->limit(7)->get();

        // Perlu Tindakan: cases needing rework — rejected Peguam Panel applications
        // (legacy status_agihan 9/14) OR unassigned open cases awaiting agihan.
        $perluTindakan = Form::query()
            ->whereNull('tarikh_tutup_fail')
            ->where(function ($w) {
                $w->whereIn('status_agihan', ['9', '14'])
                    ->orWhere(fn ($s) => $s->whereNull('nama_pegawai_yang_dapat_kes')->orWhere('nama_pegawai_yang_dapat_kes', ''));
            })
            ->orderByDesc('id')
            ->limit(6)
            ->get(['id', 'nama', 'no_fail', 'status', 'status_agihan', 'cawangan']);

        return view('system.utama', compact('stats', 'recentKes', 'recentAudit', 'perluTindakan'));
    }
}
