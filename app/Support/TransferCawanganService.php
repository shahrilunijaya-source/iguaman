<?php

declare(strict_types=1);

namespace App\Support;

use App\Events\PemindahanCawanganDimulakan;
use App\Models\Cawangan;
use App\Models\Form;
use App\Models\KhidmatNasihat;
use App\Models\PemindahanCawangan;
use App\Models\Scopes\CawanganScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * W7 + W3 - shared branch-transfer engine for cases (forms) and advisories
 * (khidmat_nasihat). Mirrors the W5 AgihanLuarService lock-based pattern:
 * each public method is ONE guarded transition under DB::transaction +
 * lockForUpdate, with Audit::log AFTER the txn. Guards throw RuntimeException
 * (the controller converts to a redirect-with-error).
 *
 * Model: the record's branch label MOVES at {@see pindahKes()}/{@see pindahKn()}
 * time (so the destination sees it immediately), while the origin branch is
 * stamped onto cawangan_asal / cawangan_asal_id so it keeps the record in its
 * worklists (D2 dual-branch). {@see terima()} is the destination's
 * acknowledgement; {@see tolak()} reverses the label back to the origin.
 *
 * Branch identity is asymmetric: forms key on `cawangan` (a NAME string, scoped
 * by CawanganScope), khidmat_nasihat keys on `cawangan_id` (an int, no global
 * scope) - so each path resolves and guards on its own column.
 */
class TransferCawanganService
{
    /** Initiate a case transfer: move forms.cawangan to the target, retain the origin. */
    public function pindahKes(Form $kes, int $tujuanId, string $sebab, User $actor): PemindahanCawangan
    {
        $pindah = DB::transaction(function () use ($kes, $tujuanId, $sebab, $actor): PemindahanCawangan {
            $fresh = Form::withoutGlobalScope(CawanganScope::class)->whereKey($kes->id)->lockForUpdate()->firstOrFail();

            $this->assertKesBranch($fresh, $actor);
            $this->assertNoPending(PemindahanCawangan::JENIS_KES, $fresh->id);

            $tujuan = Cawangan::findOrFail($tujuanId);
            $asalNama = (string) $fresh->cawangan;

            if (trim($tujuan->nama) === trim($asalNama)) {
                throw new RuntimeException('Cawangan tujuan mestilah berbeza daripada cawangan semasa.');
            }

            $asalId = Cawangan::where('nama', $asalNama)->value('id');

            $fresh->update([
                'cawangan' => $tujuan->nama,
                'cawangan_asal' => $asalNama,
            ]);

            return PemindahanCawangan::create([
                'jenis_rekod' => PemindahanCawangan::JENIS_KES,
                'id_rekod' => $fresh->id,
                'cawangan_asal' => $asalNama,
                'cawangan_asal_id' => $asalId,
                'cawangan_tujuan' => $tujuan->nama,
                'cawangan_tujuan_id' => $tujuan->id,
                'sebab' => $sebab,
                'status' => PemindahanCawangan::STATUS_DIPINDAH,
                'tarikh_pindah' => now(),
                'dipindah_oleh' => $actor->name,
            ]);
        });

        Audit::log('forms', $kes->id, Audit::UPDATE,
            "Kes dipindahkan ke cawangan {$pindah->cawangan_tujuan} (kes #{$kes->id}).", $actor->name);

        // W21 - notify the destination branch (queued; never rolls back the transfer).
        PemindahanCawanganDimulakan::dispatch($pindah);

        return $pindah;
    }

    /** Initiate a KN transfer: move khidmat_nasihat.cawangan_id to the target, retain the origin. */
    public function pindahKn(KhidmatNasihat $kn, int $tujuanId, string $sebab, User $actor): PemindahanCawangan
    {
        $pindah = DB::transaction(function () use ($kn, $tujuanId, $sebab, $actor): PemindahanCawangan {
            $fresh = KhidmatNasihat::whereKey($kn->id)->lockForUpdate()->firstOrFail();

            $this->assertKnBranch($fresh, $actor);
            $this->assertKnBolehPindah($fresh);
            $this->assertNoPending(PemindahanCawangan::JENIS_KN, $fresh->id);

            $tujuan = Cawangan::findOrFail($tujuanId);
            // Keep the origin id null-faithful (no (int) coercion to 0) so tolak() can
            // restore the exact original cawangan_id; a KN with no branch can't transfer.
            $asalId = $fresh->cawangan_id;

            if ($asalId === null) {
                throw new RuntimeException('Khidmat Nasihat ini tiada cawangan asal - tidak boleh dipindahkan.');
            }

            if ($tujuan->id === (int) $asalId) {
                throw new RuntimeException('Cawangan tujuan mestilah berbeza daripada cawangan semasa.');
            }

            $asalNama = Cawangan::find($asalId)?->nama;

            $fresh->update([
                'cawangan_id' => $tujuan->id,
                'cawangan_asal_id' => $asalId,
            ]);

            return PemindahanCawangan::create([
                'jenis_rekod' => PemindahanCawangan::JENIS_KN,
                'id_rekod' => $fresh->id,
                'cawangan_asal' => $asalNama,
                'cawangan_asal_id' => $asalId,
                'cawangan_tujuan' => $tujuan->nama,
                'cawangan_tujuan_id' => $tujuan->id,
                'sebab' => $sebab,
                'status' => PemindahanCawangan::STATUS_DIPINDAH,
                'tarikh_pindah' => now(),
                'dipindah_oleh' => $actor->name,
            ]);
        });

        Audit::log('khidmat_nasihat', $kn->id, Audit::UPDATE,
            "Khidmat Nasihat dipindahkan ke cawangan {$pindah->cawangan_tujuan} (KN #{$kn->id}).", $actor->name);

        // W21 - notify the destination branch (queued; never rolls back the transfer).
        PemindahanCawanganDimulakan::dispatch($pindah);

        return $pindah;
    }

    /** Destination accepts the transfer (acknowledgement only - the label already moved). */
    public function terima(PemindahanCawangan $pindah, User $actor): void
    {
        $rekodId = DB::transaction(function () use ($pindah, $actor): int {
            $fresh = PemindahanCawangan::whereKey($pindah->id)->lockForUpdate()->firstOrFail();

            $this->assertTujuanBranch($fresh, $actor);
            $this->assertPending($fresh);

            $fresh->update([
                'status' => PemindahanCawangan::STATUS_DITERIMA,
                'tarikh_terima' => now(),
                'diterima_oleh' => $actor->name,
            ]);

            return (int) $fresh->id_rekod;
        });

        Audit::log($this->auditTable($pindah), $rekodId, Audit::UPDATE,
            "Pemindahan ke cawangan {$pindah->cawangan_tujuan} diterima.", $actor->name);
    }

    /** Destination rejects the transfer - reverse the branch label back to the origin. */
    public function tolak(PemindahanCawangan $pindah, string $sebab, User $actor): void
    {
        $rekodId = DB::transaction(function () use ($pindah, $sebab, $actor): int {
            $fresh = PemindahanCawangan::whereKey($pindah->id)->lockForUpdate()->firstOrFail();

            $this->assertTujuanBranch($fresh, $actor);
            $this->assertPending($fresh);

            // Reverse the moved label back to the origin branch.
            if ($fresh->jenis_rekod === PemindahanCawangan::JENIS_KES) {
                Form::withoutGlobalScope(CawanganScope::class)
                    ->whereKey($fresh->id_rekod)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->update([
                        'cawangan' => $fresh->cawangan_asal,
                        'cawangan_asal' => null,
                    ]);
            } else {
                KhidmatNasihat::whereKey($fresh->id_rekod)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->update([
                        'cawangan_id' => $fresh->cawangan_asal_id,
                        'cawangan_asal_id' => null,
                    ]);
            }

            $fresh->update([
                'status' => PemindahanCawangan::STATUS_DITOLAK,
                'tarikh_terima' => now(),
                'sebab_tolak' => $sebab,
                'diterima_oleh' => $actor->name,
            ]);

            return (int) $fresh->id_rekod;
        });

        Audit::log($this->auditTable($pindah), $rekodId, Audit::REJECT,
            "Pemindahan ke cawangan {$pindah->cawangan_tujuan} ditolak - dikembalikan ke {$pindah->cawangan_asal}.", $actor->name);
    }

    /**
     * Transfers visible to a user's branch (as origin OR destination), newest first.
     * Staff pinned to a branch see only their own in/out moves; view-all / no-branch
     * see everything. Caller paginates.
     */
    public function listForUser(?User $user): Builder
    {
        $nama = $this->branchName($user);
        $id = $this->branchId($user);

        return PemindahanCawangan::query()
            ->when($nama !== null, fn ($w) => $w->where(fn ($b) => $b
                ->where('cawangan_tujuan', $nama)
                ->orWhere('cawangan_asal', $nama)
                ->orWhere('cawangan_tujuan_id', $id)
                ->orWhere('cawangan_asal_id', $id)))
            ->orderByDesc('id');
    }

    /** Count of incoming pending transfers for a user's branch (inbox badge). */
    public function inboxCount(?User $user): int
    {
        return (int) $this->listForUser($user)
            ->where('status', PemindahanCawangan::STATUS_DIPINDAH)
            ->where(fn ($w) => $this->matchTujuan($w, $user))
            ->count();
    }

    /** True when this user may accept/reject a pending transfer (record-type capability + destination branch). */
    public function canActOn(PemindahanCawangan $pindah, ?User $user): bool
    {
        if ($user === null || ! $pindah->isPending()) {
            return false;
        }

        // Record-type capability: a case transfer needs kes.pindah, a KN transfer
        // needs khidmat.manage (the permission that initiates that side). Without
        // this a ppuu (kes.pindah, no khidmat.manage) could mutate a khidmat_nasihat
        // row it cannot otherwise touch.
        $perm = $pindah->jenis_rekod === PemindahanCawangan::JENIS_KES ? 'kes.pindah' : 'khidmat.manage';
        if (! $user->can($perm)) {
            return false;
        }

        // Branch alignment: HQ / view-all (no pinned branch) oversee every branch and
        // may act on any transfer (the established view-all semantics across services);
        // a branch-pinned officer may act only when their branch is the destination.
        $nama = $this->branchName($user);
        if ($nama === null) {
            return true;
        }

        return $pindah->jenis_rekod === PemindahanCawangan::JENIS_KES
            ? trim((string) $pindah->cawangan_tujuan) === trim($nama)
            : (int) $pindah->cawangan_tujuan_id === $this->branchId($user);
    }

    // ---- guards ---------------------------------------------------------------

    /** A branch-pinned officer may transfer only a case in their own branch. */
    private function assertKesBranch(Form $kes, User $actor): void
    {
        $nama = $this->branchName($actor);

        if ($nama !== null && trim((string) $kes->cawangan) !== trim($nama)) {
            throw new RuntimeException('Kes ini bukan di bawah cawangan anda.');
        }
    }

    /** A branch-pinned officer may transfer only a KN in their own branch. */
    private function assertKnBranch(KhidmatNasihat $kn, User $actor): void
    {
        $branchId = $this->branchId($actor);

        if ($branchId !== null && (int) $kn->cawangan_id !== $branchId) {
            throw new RuntimeException('Khidmat Nasihat ini bukan di bawah cawangan anda.');
        }
    }

    /** A KN draft/cancelled record cannot be transferred. */
    private function assertKnBolehPindah(KhidmatNasihat $kn): void
    {
        if (in_array($kn->status_kn, [KhidmatNasihat::STATUS_DRAF, KhidmatNasihat::STATUS_BATAL], true)) {
            throw new RuntimeException('Hanya Khidmat Nasihat yang telah dihantar (bukan draf/batal) boleh dipindahkan.');
        }
    }

    /** Only the destination branch may accept/reject (the table has no CawanganScope). */
    private function assertTujuanBranch(PemindahanCawangan $pindah, User $actor): void
    {
        if (! $this->canActOn($pindah, $actor)) {
            throw new RuntimeException('Pemindahan ini bukan untuk cawangan anda.');
        }
    }

    private function assertPending(PemindahanCawangan $pindah): void
    {
        if (! $pindah->isPending()) {
            throw new RuntimeException('Pemindahan ini telah selesai diproses.');
        }
    }

    /** Block a second open transfer for the same record. */
    private function assertNoPending(string $jenis, int $idRekod): void
    {
        $exists = PemindahanCawangan::where('jenis_rekod', $jenis)
            ->where('id_rekod', $idRekod)
            ->where('status', PemindahanCawangan::STATUS_DIPINDAH)
            ->exists();

        if ($exists) {
            throw new RuntimeException('Rekod ini sudah ada pemindahan yang menunggu untuk diterima.');
        }
    }

    // ---- branch resolution ----------------------------------------------------

    /** The branch NAME a staff user is pinned to, or null for view-all / no-branch / lawyers. */
    private function branchName(?User $user): ?string
    {
        if ($user === null || ! $user->isStaff() || ! filled($user->cawangan) || $user->can('cawangan.view-all')) {
            return null;
        }

        return $user->cawangan;
    }

    /** The cawangan_id a staff user is pinned to, or null for view-all / no-branch / lawyers. */
    private function branchId(?User $user): ?int
    {
        $nama = $this->branchName($user);

        return $nama === null ? null : Cawangan::where('nama', $nama)->value('id');
    }

    /** Constrain a query to transfers whose DESTINATION is this user's branch. */
    private function matchTujuan(Builder $w, ?User $user): Builder
    {
        $nama = $this->branchName($user);

        if ($nama === null) {
            return $w; // view-all sees all destinations
        }

        return $w->where(fn ($b) => $b
            ->where('cawangan_tujuan', $nama)
            ->orWhere('cawangan_tujuan_id', $this->branchId($user)));
    }

    private function auditTable(PemindahanCawangan $pindah): string
    {
        return $pindah->jenis_rekod === PemindahanCawangan::JENIS_KES ? 'forms' : 'khidmat_nasihat';
    }
}
