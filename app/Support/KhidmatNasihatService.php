<?php

namespace App\Support;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use App\Models\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * KN creation + appointment slot lifecycle, shared by the staff wizard
 * (KhidmatNasihatController) and the public portal (Awam\PermohonanController).
 * Extracted from KhidmatNasihatController so both paths stay in lockstep.
 */
class KhidmatNasihatService
{
    public function create(array $attributes): KhidmatNasihat
    {
        return DB::transaction(function () use ($attributes) {
            $kn = KhidmatNasihat::create($attributes);
            $kn->update(['no_permohonan' => $this->nextNoPermohonan($kn)]);

            return $kn;
        });
    }

    public function bookSlot(KhidmatNasihat $khidmat, string $tarikh, string $masa, string $oleh): TemuJanji
    {
        return DB::transaction(function () use ($khidmat, $tarikh, $masa, $oleh) {
            $slot = SlotTemuJanji::query()
                ->where('cawangan_id', $khidmat->cawangan_id)
                ->whereDate('tarikh_slot', $tarikh)
                ->whereRaw("DATE_FORMAT(masa_mula, '%H:%i') = ?", [$masa])
                ->where('is_temujanji', false)
                ->where('status_aktif', true)
                ->lockForUpdate()
                ->first();

            abort_if($slot === null, 422, 'Slot temu janji tidak lagi tersedia. Sila pilih masa lain.');

            $temu = TemuJanji::create([
                'id_khidmat_nasihat' => $khidmat->id,
                'slot_temu_janji_id' => $slot->id,
                'cawangan_id' => $khidmat->cawangan_id,
                'tarikh_temu_janji' => $slot->tarikh_slot,
                'masa_mula' => $slot->masa_mula,
                'masa_akhir' => $slot->masa_akhir,
                'status' => 'MENUNGGU',
                'cipta_oleh' => $oleh,
            ]);

            $slot->update(['is_temujanji' => true]);
            $khidmat->update(['id_temu_janji' => $temu->id]);

            return $temu;
        });
    }

    public function releaseSlot(KhidmatNasihat $khidmat): void
    {
        DB::transaction(function () use ($khidmat) {
            $temu = $khidmat->temuJanji()->first();
            if ($temu === null) {
                return;
            }

            SlotTemuJanji::whereKey($temu->slot_temu_janji_id)->update(['is_temujanji' => false]);
            $temu->update(['status' => 'BATAL']);
            $khidmat->update(['id_temu_janji' => null]);
        });
    }

    public function reschedule(KhidmatNasihat $khidmat, string $tarikh, string $masa, string $oleh): TemuJanji
    {
        return DB::transaction(function () use ($khidmat, $tarikh, $masa, $oleh) {
            $this->releaseSlot($khidmat);

            return $this->bookSlot($khidmat, $tarikh, $masa, $oleh);
        });
    }

    /**
     * ARCH-01 — W1 fee-waiver proof, moved out of KhidmatNasihatController. Stores the
     * optional waiver document when the application is fee-exempt and links it via
     * khidmat_nasihat.id_lampiran_waiver.
     */
    public function storeWaiver(KhidmatNasihat $khidmat, Request $request): void
    {
        if (! $request->boolean('is_percuma') || ! $request->hasFile('lampiran_waiver')) {
            return;
        }

        $row = $this->storeLampiran($khidmat, $request->file('lampiran_waiver'), 'Bukti Pengecualian Bayaran');
        $khidmat->update(['id_lampiran_waiver' => $row->id]);

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::UPDATE,
            "Bukti pengecualian bayaran dimuat naik: {$row->nama}");
    }

    /**
     * ARCH-01 — persist a KN-linked document on the W6 repository disk (mirrors
     * LampiranController) and link it via uploaded_files.id_khidmat. The caller wires
     * the specific FK column (id_lampiran_waiver / id_lampiran_resit).
     */
    public function storeLampiran(KhidmatNasihat $khidmat, \Illuminate\Http\UploadedFile $file, string $nama): UploadedFile
    {
        $path = $file->store('lampiran', config('filesystems.lampiran_disk', 'repositori'));

        return UploadedFile::create([
            'nama' => "{$nama} — {$khidmat->no_permohonan}",
            'file_name' => basename($path),
            'file_path' => $path,
            'file_type' => strtolower($file->getClientOriginalExtension() ?: $file->extension()),
            'id_khidmat' => $khidmat->id,
            'uploaded_at' => now(),
        ]);
    }

    private function nextNoPermohonan(KhidmatNasihat $khidmat): string
    {
        $cawangan = $khidmat->cawangan_id ? Cawangan::find($khidmat->cawangan_id) : null;
        $kod = $cawangan?->kod ?: 'JBG';
        $year = now()->year;

        $seq = KhidmatNasihat::where('cawangan_id', $khidmat->cawangan_id)
            ->whereYear('created_at', $year)
            ->where('id', '<=', $khidmat->id)
            ->count();

        return sprintf('KN/%s/%d/%04d', $kod, $year, max(1, $seq));
    }
}
