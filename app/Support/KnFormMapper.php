<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Requests\KhidmatNasihatRequest;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;

/**
 * ARCH-01 — validated Khidmat Nasihat input → khidmat_nasihat columns, extracted from
 * KhidmatNasihatController::mapInput. Keeps the wakil/mahkamah context branching, the
 * computed intake fee, and the session-authoritative screening outcome in one testable
 * place instead of inline in the transport layer.
 */
class KnFormMapper
{
    /** Validated input → khidmat_nasihat columns (incl. computed fee + status). */
    public static function map(KhidmatNasihatRequest $request): array
    {
        $v = $request->validated();
        $isPercuma = $request->boolean('is_percuma');
        $isWakil = $request->isWakil();
        $isMahkamah = $request->isMahkamah();
        $jenisWakil = $isWakil ? ($v['jenis_wakil'] ?? null) : null;
        $kategori = RefKategoriKn::find($v['id_kategori'] ?? null);

        // Penjara/JKM wakil contexts are fee-exempt (RM0); mahkamah uses the matrix.
        $fee = KhidmatBayaran::kira($kategori?->jenis_kategori, $v['jumlah_pendapatan'] ?? null, $isPercuma, $jenisWakil);

        // Screening outcome: trust the SESSION (set by the saringan gate), not the
        // client-supplied hidden fields, so a tampered POST can't fake a pass.
        $saringan = $isWakil ? null : session('saringan');

        return [
            'jenis_permohonan' => $isWakil ? 'SEBAGAI_WAKIL' : 'DIRI_SENDIRI',
            'jenis_wakil' => $jenisWakil,
            // W1 — explicit source tag for KPI/reporting (prison/clinic vs public).
            'applicant_source' => KhidmatNasihat::deriveSource($isWakil ? 'SEBAGAI_WAKIL' : 'DIRI_SENDIRI', $jenisWakil),
            'no_pengenalan_wakil' => $isWakil ? ($v['no_pengenalan_wakil'] ?? null) : null,
            'jawatan_wakil' => $isWakil ? ($v['jawatan_wakil'] ?? null) : null,
            'nama_diwakili' => $isWakil ? ($v['nama_diwakili'] ?? null) : null,
            'id_pengenalan_diwakili' => $isWakil ? ($v['id_pengenalan_diwakili'] ?? null) : null,
            'jenis_mahkamah_pihak' => $isMahkamah ? ($v['jenis_mahkamah_pihak'] ?? null) : null,
            'id_mahkamah' => $isMahkamah ? ($v['id_mahkamah'] ?? null) : null,
            'saringan_jenis' => $saringan['jenis'] ?? ($v['saringan_jenis'] ?? null),
            'saringan_lulus' => (bool) ($saringan['lulus'] ?? false),
            'is_laluan_sumbangan' => (bool) ($saringan['sumbangan'] ?? false),
            'nama_mangsa' => $v['nama_mangsa'],
            'id_pengenalan_mangsa' => $v['id_pengenalan_mangsa'] ?? null,
            'jenis_pengenalan_mangsa' => $v['jenis_pengenalan_mangsa'] ?? null,
            'jantina_mangsa' => $v['jantina_mangsa'] ?? null,
            'umur_mangsa' => $v['umur_mangsa'] ?? null,
            'bangsa' => $v['bangsa'] ?? null,
            'agama' => $v['agama'] ?? null,
            'tarikh_lahir_mangsa' => $v['tarikh_lahir_mangsa'] ?? null,
            'nama_wakil' => $v['nama_wakil'] ?? null,
            'alamat_surat1' => $v['alamat_surat1'] ?? null,
            'alamat_surat2' => $v['alamat_surat2'] ?? null,
            'alamat_surat3' => $v['alamat_surat3'] ?? null,
            'poskod' => $v['poskod'] ?? null,
            'cawangan_id' => $v['cawangan_id'],
            'id_kategori' => $v['id_kategori'] ?? null,
            'id_subkategori' => $v['id_subkategori'] ?? null,
            'id_negeri' => $v['id_negeri'] ?? null,
            'jenis_kes' => $v['jenis_kes'] ?? null,
            'jumlah_pendapatan' => $v['jumlah_pendapatan'] ?? null,
            'ulasan_permohonan' => $v['ulasan_permohonan'] ?? null,
            'jumlah_bayaran' => $fee,
            'is_percuma' => $isPercuma,
            'perakuan' => $request->isHantar() ? $request->boolean('perakuan') : false,
            'status_kn' => $request->isHantar() ? KhidmatNasihat::STATUS_BAHARU : KhidmatNasihat::STATUS_DRAF,
            'cipta_oleh' => $request->user()->name,
            'kemaskini_oleh' => $request->user()->name,
        ];
    }
}
