<?php

namespace App\Support;

use App\Models\ButiranPeguamPanel2;
use App\Models\ButiranPeguamPanel6;
use App\Models\Form;
use App\Models\User;

/**
 * Bidang Pengkhususan add/drop lifecycle (legacy profil-kemaskinibidangkes.php +
 * maklumat-kemaskini-kes.php). A lawyer requests to add or drop a practice area; the request
 * is recommended by the Pengarah and finalised by the Ketua Pengarah. Dropping is blocked
 * while the lawyer still handles an active case in that category.
 *
 *   add:  (lawyer) 4 → (Pengarah) 9 → (KP) 2 active
 *   drop: (lawyer) 3 → (Pengarah) 7 → (KP) row deleted
 */
class PengkhususanService
{
    /** Lawyer requests to add a practice area (idempotent - ignores an already-present area). */
    public function requestAdd(string $kpBaru, string $category, string $value, User $actor): ?ButiranPeguamPanel6
    {
        $existing = ButiranPeguamPanel6::where('kpBaru', $kpBaru)->where('checkbox_value', $value)->first();
        if ($existing) {
            return null; // already present or already pending
        }

        return ButiranPeguamPanel6::create([
            'kpBaru' => $kpBaru,
            'category' => $category,
            'checkbox_value' => $value,
            'checkbox_value_status' => ButiranPeguamPanel6::ADD_MOHON,
            'jenisKemaskini' => 'TAMBAH',
            'modifiedBy' => $actor->name,
            'modifiedDate' => now()->toDateString(),
        ]);
    }

    /** Lawyer requests to drop an active practice area - blocked if an active case uses it. */
    public function requestDrop(ButiranPeguamPanel6 $row, User $actor): void
    {
        abort_unless(in_array($row->checkbox_value_status, ButiranPeguamPanel6::AKTIF_STATES, true), 422, 'Hanya bidang aktif boleh digugurkan.');
        abort_if($this->hasActiveCaseInCategory($row->kpBaru, $row->category), 422, 'Tidak boleh gugur - masih ada kes aktif dalam kategori ini.');

        $row->update([
            'checkbox_value_status' => ButiranPeguamPanel6::DROP_MOHON,
            'jenisKemaskini' => 'GUGUR',
            'modifiedBy' => $actor->name,
            'modifiedDate' => now()->toDateString(),
        ]);
    }

    /** Pengarah recommends (3→7 / 4→9) or rejects a request. */
    public function pengarahReview(ButiranPeguamPanel6 $row, bool $recommend, ?string $ulasan, User $actor): void
    {
        abort_unless(in_array($row->checkbox_value_status, ButiranPeguamPanel6::PENGARAH_PENDING, true), 422, 'Tiada permohonan menunggu sokongan.');

        if (! $recommend) {
            // Reject: drop request reverts to active; add request is removed.
            if ($row->checkbox_value_status === ButiranPeguamPanel6::DROP_MOHON) {
                $row->update(['checkbox_value_status' => ButiranPeguamPanel6::AKTIF, 'ulasanPengarah' => $ulasan, 'modifiedBy' => $actor->name, 'modifiedDate' => now()->toDateString()]);
            } else {
                $row->delete();
            }

            return;
        }

        $row->update([
            'checkbox_value_status' => $row->checkbox_value_status === ButiranPeguamPanel6::DROP_MOHON
                ? ButiranPeguamPanel6::DROP_DISOKONG
                : ButiranPeguamPanel6::ADD_DISOKONG,
            'ulasanPengarah' => $ulasan,
            'modifiedBy' => $actor->name,
            'modifiedDate' => now()->toDateString(),
        ]);
    }

    /** Ketua Pengarah finalises (7→delete / 9→active) or rejects. */
    public function kpDecide(ButiranPeguamPanel6 $row, bool $approve, User $actor): void
    {
        abort_unless(in_array($row->checkbox_value_status, ButiranPeguamPanel6::KP_PENDING, true), 422, 'Tiada permohonan menunggu kelulusan.');

        $isDrop = $row->checkbox_value_status === ButiranPeguamPanel6::DROP_DISOKONG;

        if ($approve) {
            if ($isDrop) {
                $row->delete();                       // drop approved → remove the area
            } else {
                $row->update(['checkbox_value_status' => ButiranPeguamPanel6::AKTIF, 'modifiedBy' => $actor->name, 'modifiedDate' => now()->toDateString()]);
            }

            return;
        }

        // Reject: drop reverts to active; add is removed.
        if ($isDrop) {
            $row->update(['checkbox_value_status' => ButiranPeguamPanel6::AKTIF, 'modifiedBy' => $actor->name, 'modifiedDate' => now()->toDateString()]);
        } else {
            $row->delete();
        }
    }

    /** Pending-request count for the staff notification badge. */
    public static function pendingCount(): int
    {
        return ButiranPeguamPanel6::whereIn('checkbox_value_status',
            array_merge(ButiranPeguamPanel6::PENGARAH_PENDING, ButiranPeguamPanel6::KP_PENDING))->count();
    }

    /** True if the lawyer still handles an active case in the given category. */
    private function hasActiveCaseInCategory(string $kpBaru, ?string $category): bool
    {
        $nama = ButiranPeguamPanel2::where('kpBaru', $kpBaru)->value('namaPeguam');
        if (! $nama || ! $category) {
            return false;
        }

        return Form::where('nama_pegawai_yang_dapat_kes', $nama)
            ->whereIn('status_agihan', StatusAgihan::bucketValues([StatusAgihan::DITAWARKAN, StatusAgihan::DITERIMA]))
            ->where('kategori_kes', 'like', '%'.$category.'%')
            ->exists();
    }
}
