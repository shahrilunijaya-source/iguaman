<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Requests\PeguamDaftarRequest;
use App\Models\ButiranPeguamPanel2;
use App\Models\ButiranPeguamPanel3;
use App\Models\ButiranPeguamPanel4;
use App\Models\ButiranPeguamPanel5;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * CODE-05 — self-service panel-lawyer profile update, extracted from
 * PeguamController::updateProfil. The per-table field whitelists + fill/save across
 * butiran_peguam_panel_2/_3/_4/_5 and the document re-upload live here so the
 * controller stays transport-only and the mapping is unit-testable.
 */
class PeguamProfilUpdateService
{
    /**
     * Persist validated profile changes for a lawyer (keyed by kpBaru), including
     * document re-uploads. One transaction: partial saves must not survive a failure.
     */
    public function update(Request $request, array $d, string $kp, User $user): void
    {
        DB::transaction(function () use ($request, $d, $kp, $user) {
            $p2 = ButiranPeguamPanel2::firstOrNew(['kpBaru' => $kp]);
            $p2->fill(Arr::only($d, [
                'noTelBimbit', 'emelPeguam', 'kelulusanAkademik', 'tarikhDiterimaMasuk',
                'tarikhDiterimaMasukSyarie', 'tahunPengalaman', 'tahunPengalamanSyarie',
                'bilanganKes', 'keteranganKes',
            ]));
            if (! $p2->exists) {
                $p2->namaPeguam = $user->name;
                $p2->permohonan_status = '0';
                $p2->tarikhMohon = now();
            }
            $p2->save();

            $cso = [];
            foreach (range(1, 5) as $i) {
                array_push($cso, "csoNumber{$i}", "cso{$i}Tauliah", "cso{$i}Mula", "cso{$i}Akhir", "lokasiBerguam{$i}");
            }
            ButiranPeguamPanel3::firstOrNew(['kpBaru' => $kp])->fill(Arr::only($d, array_merge([
                'clpNumber', 'clpMula', 'clpAkhir',
                'ybgk_kelulusan', 'ybgk_tarikhLulus_A', 'ybgk_tarikhLulus_B', 'ybgk_daftar',
                'adr_penimbangtara', 'adr_pengantara',
                'sijilAhli_nombor', 'sijilAhli_namaBadan', 'sijilAhli_mula', 'sijilAhli_akhir',
                'sijilAkreditasi_nombor', 'sijilAkreditasi_namaBadan', 'sijilAkreditasi_mula', 'sijilAkreditasi_akhir',
                'eVendor_daftar', 'eVendor_ID',
            ], $cso)))->save();

            ButiranPeguamPanel4::firstOrNew(['kpBaru' => $kp])->fill(Arr::only($d, [
                'namaFirma', 'alamatFirma1', 'alamatFirma2', 'alamatFirma3', 'poskodFirma',
                'bandarFirma', 'negeriFirma', 'noTelFirma', 'noFaksFirma',
                'namaInsurans', 'noPolisi', 'amaunPerlindungan', 'polisiMula', 'polisiAkhir',
            ]))->save();

            ButiranPeguamPanel5::firstOrNew(['kpBaru' => $kp])->fill(Arr::only($d, [
                'namaBank', 'noAkaunBank', 'alamatBank1', 'alamatBank2', 'alamatBank3',
                'poskodBank', 'bandarBank', 'negeriBank',
            ]))->save();

            // All 18 docs editable (cso4/cso5 included — fixes the legacy profilUpdate bug).
            LawyerDocuments::store($request, $kp, $p2->namaPeguam ?? $user->name, array_keys(PeguamDaftarRequest::DOC_TYPES));
        });
    }
}
