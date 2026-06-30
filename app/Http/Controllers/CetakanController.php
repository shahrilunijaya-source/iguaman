<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Models\PeguamPanel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Cetakan (printouts) — per-case PDFs via dompdf, replacing legacy FPDF cetakan files.
// Streamed inline so staff can preview + print directly from the browser.
class CetakanController extends Controller
{
    /** Full case summary (Ringkasan Kes). */
    public function ringkasan(Request $request, Form $kes): Response
    {
        $kes->load('laporanKes');

        return $this->pdf($request, 'cetakan.ringkasan', "ringkasan-kes-{$kes->id}", ['kes' => $kes]);
    }

    /** Lawyer assignment letter (Surat Penugasan Peguam Panel). */
    public function agihan(Request $request, Form $kes): Response
    {
        if (blank($kes->nama_pegawai_yang_dapat_kes)) {
            return redirect()->route('kes.show', $kes)->withErrors(['cetak' => 'Kes ini belum diagih kepada mana-mana peguam panel.']);
        }

        $peguam = PeguamPanel::where('nama_peguam', $kes->nama_pegawai_yang_dapat_kes)->first();

        return $this->pdf($request, 'cetakan.agihan', "surat-penugasan-{$kes->id}", [
            'kes' => $kes,
            'peguam' => $peguam,
            'tarikhCetak' => now()->format('d/m/Y'),
        ]);
    }

    /** Court case report (Laporan Kes Mahkamah). */
    public function laporan(Request $request, Form $kes): Response
    {
        $kes->load('laporanKes');

        return $this->pdf($request, 'cetakan.laporan', "laporan-kes-{$kes->id}", ['kes' => $kes]);
    }

    /** W16 — case-closure letter (Surat Penutupan Fail), available once the file is closed. */
    public function penutupan(Request $request, Form $kes): Response
    {
        if (blank($kes->tarikh_tutup_fail)) {
            return redirect()->route('kes.show', $kes)->withErrors(['cetak' => 'Kes ini belum ditutup.']);
        }

        return $this->pdf($request, 'cetakan.penutupan', "penutupan-kes-{$kes->id}", [
            'kes' => $kes,
            'tarikhCetak' => now()->format('d/m/Y'),
        ]);
    }

    /** W14 — legal-aid certificate (Perakuan Bantuan Guaman), interim or muktamad. */
    public function perakuan(Request $request, Form $kes): Response
    {
        if (blank($kes->status_perakuan)) {
            return redirect()->route('pembelaan.show', $kes)->withErrors(['cetak' => 'Perakuan belum dikeluarkan untuk kes ini.']);
        }

        return $this->pdf($request, 'cetakan.perakuan', "perakuan-kes-{$kes->id}", [
            'kes' => $kes,
            'tarikhCetak' => now()->format('d/m/Y'),
        ]);
    }

    /** Shared dompdf render: inject letterhead meta + stream inline. */
    private function pdf(Request $request, string $view, string $filename, array $data): Response
    {
        $pdf = Pdf::loadView($view, $data + [
            'dijana' => now()->format('d/m/Y H:i'),
            'oleh' => $request->user()->name,
        ])->setPaper('a4');

        return $pdf->stream($filename.'.pdf');
    }
}
