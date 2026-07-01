<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\LejarTuntutanBayaran;
use App\Models\PeguamPanel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * W5 - assign a completed (SELESAI) Khidmat Nasihat to an EXTERNAL panel lawyer.
 *
 * Two modes over the status_agihan_pl machine (own column; never overloads status_kn):
 *   - GRAB:   officer opens the KN to a pool (BUKA_GRAB); any active panel lawyer
 *             self-claims first-come within {@see GRAB_DAYS} days (-> DIAGIH).
 *             Unclaimed grabs expire to LUPUT via the scheduled grab:tamat-luput command.
 *   - ASSIGN: officer directly assigns a lawyer picked from the W11 workload shortlist
 *             ({@see PeguamShortlistService}) -> DIAGIH.
 *
 * On a successful claim/assign a PEGUAM_LUAR claim row is seeded in the W15 ledger
 * (idempotent via {@see LejarTuntutanService::fromPeguamLuar}). Mirrors the guard +
 * DB::transaction + lockForUpdate style of {@see KhidmatProsesService}. KhidmatNasihat
 * has no CawanganScope, so officer branch isolation is applied here explicitly.
 */
class AgihanLuarService
{
    /** Days a grabbed KN stays claimable before it expires (LUPUT). */
    public const GRAB_DAYS = 7;

    public const REASON_LUPUT = 'Tamat tempoh grab (7 hari) tanpa tuntutan oleh mana-mana peguam panel.';

    private const ACTOR = 'Sistem (Auto Tamat Grab Luput)';

    /** Officer opens a SELESAI KN to the grab pool (-> BUKA_GRAB), resetting any prior claim. */
    public function bukaGrab(KhidmatNasihat $kn, User $actor): void
    {
        DB::transaction(function () use ($kn, $actor) {
            $fresh = KhidmatNasihat::whereKey($kn->id)->lockForUpdate()->firstOrFail();
            $this->assertBranch($fresh, $actor);
            $this->assertBolehAgih($fresh);

            $fresh->update([
                'status_agihan_pl' => KhidmatNasihat::PL_BUKA_GRAB,
                'mod_agihan_peguam' => KhidmatNasihat::MOD_GRAB,
                'tarikh_buka_grab' => now(),
                'id_peguam_panel' => null,
                'tarikh_agihan_pl' => null,
            ]);
        });

        Audit::log('khidmat_nasihat', $kn->id, Audit::UPDATE,
            "KN dibuka untuk grab peguam panel (KN #{$kn->id}).", $actor->name);
    }

    /** Officer directly assigns an external lawyer (from the W11 shortlist) -> DIAGIH + ledger. */
    public function assign(KhidmatNasihat $kn, PeguamPanel $peguam, User $actor): void
    {
        DB::transaction(function () use ($kn, $peguam, $actor) {
            $fresh = KhidmatNasihat::whereKey($kn->id)->lockForUpdate()->firstOrFail();
            $this->assertBranch($fresh, $actor);
            $this->assertBolehAgih($fresh);
            $this->assertPeguamAktif($peguam);

            $fresh->update([
                'id_peguam_panel' => $peguam->id,
                'status_agihan_pl' => KhidmatNasihat::PL_DIAGIH,
                'mod_agihan_peguam' => KhidmatNasihat::MOD_ASSIGN,
                'tarikh_agihan_pl' => now(),
                'tarikh_buka_grab' => null,
            ]);

            app(LejarTuntutanService::class)->fromPeguamLuar($fresh, $peguam, $actor->name);
        });

        Audit::log('khidmat_nasihat', $kn->id, Audit::UPDATE,
            "KN diagihkan kepada peguam panel {$peguam->nama_peguam} (KN #{$kn->id}).", $actor->name);
    }

    /** Lawyer self-claims an open-grab KN -> DIAGIH + ledger. Race-safe (lock + re-check). */
    public function grab(KhidmatNasihat $kn, PeguamPanel $peguam, User $actor): void
    {
        DB::transaction(function () use ($kn, $peguam, $actor) {
            $fresh = KhidmatNasihat::whereKey($kn->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status_agihan_pl !== KhidmatNasihat::PL_BUKA_GRAB) {
                throw new RuntimeException('Kes ini tidak lagi tersedia untuk grab.');
            }

            // Defense in depth: the KN must still be SELESAI, and the claiming lawyer active.
            $this->assertBolehAgih($fresh);
            $this->assertPeguamAktif($peguam);

            $fresh->update([
                'id_peguam_panel' => $peguam->id,
                'status_agihan_pl' => KhidmatNasihat::PL_DIAGIH,
                'mod_agihan_peguam' => KhidmatNasihat::MOD_GRAB,
                'tarikh_agihan_pl' => now(),
            ]);

            app(LejarTuntutanService::class)->fromPeguamLuar($fresh, $peguam, $actor->name);
        });

        Audit::log('khidmat_nasihat', $kn->id, Audit::UPDATE,
            "Kes grab dituntut oleh peguam panel {$peguam->nama_peguam} (KN #{$kn->id}).", $actor->name);
    }

    /** Open-grab KNs past the 7-day window, across all branches (scheduler runs unauthenticated). */
    public function expired(): Collection
    {
        // Compare on the full datetime (not whereDate) so the window is a precise 7×24h,
        // independent of the cron's time-of-day.
        $cutoff = now()->subDays(self::GRAB_DAYS);

        return KhidmatNasihat::query()
            ->where('status_agihan_pl', KhidmatNasihat::PL_BUKA_GRAB)
            ->whereNotNull('tarikh_buka_grab')
            ->where('tarikh_buka_grab', '<', $cutoff)
            ->get();
    }

    /** Expire every overdue grab; returns the number expired. */
    public function tamatGrabLuput(?callable $onEach = null): int
    {
        $count = 0;
        foreach ($this->expired() as $kn) {
            $this->luputkan($kn);
            $count++;
            if ($onEach) {
                $onEach($kn);
            }
        }

        return $count;
    }

    /** Expire one overdue grab (-> LUPUT), unless a lawyer claimed it in the meantime. */
    public function luputkan(KhidmatNasihat $kn): void
    {
        $changed = DB::transaction(function () use ($kn): bool {
            $fresh = KhidmatNasihat::whereKey($kn->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status_agihan_pl !== KhidmatNasihat::PL_BUKA_GRAB) {
                return false; // claimed/changed between query and lock
            }

            $fresh->update([
                'status_agihan_pl' => KhidmatNasihat::PL_LUPUT,
                'tarikh_buka_grab' => null,
            ]);

            return true;
        });

        if ($changed) {
            Audit::log('khidmat_nasihat', $kn->id, Audit::UPDATE,
                'Grab tamat tempoh (luput) - '.self::REASON_LUPUT, self::ACTOR);
        }
    }

    /**
     * Officer un-assigns a mis-assigned KN (DIAGIH -> null), clearing the lawyer link and
     * cancelling the still-DRAF PEGUAM_LUAR ledger row so the KN can be re-grabbed/re-assigned.
     */
    public function tarikSemula(KhidmatNasihat $kn, User $actor): void
    {
        DB::transaction(function () use ($kn, $actor) {
            $fresh = KhidmatNasihat::whereKey($kn->id)->lockForUpdate()->firstOrFail();
            $this->assertBranch($fresh, $actor);

            if ($fresh->status_agihan_pl !== KhidmatNasihat::PL_DIAGIH) {
                throw new RuntimeException('Hanya KN yang telah diagihkan boleh ditarik semula.');
            }

            // Cancel the untouched DRAF claim row; a submitted (DIHANTAR+) claim is left for officer handling.
            $ledger = LejarTuntutanBayaran::where('sumber', LejarTuntutanBayaran::SUMBER_PEGUAM_LUAR)
                ->where('id_khidmat_nasihat', $fresh->id)
                ->where('status_tuntutan', LejarTuntutanBayaran::STATUS_DRAF)
                ->first();

            if ($ledger !== null) {
                app(LejarTuntutanService::class)->transition($ledger, 'batal', $actor->name);
            }

            $fresh->update([
                'status_agihan_pl' => null,
                'id_peguam_panel' => null,
                'mod_agihan_peguam' => null,
                'tarikh_agihan_pl' => null,
            ]);
        });

        Audit::log('khidmat_nasihat', $kn->id, Audit::UPDATE,
            "Agihan peguam luar ditarik semula (KN #{$kn->id}).", $actor->name);
    }

    /** Officer worklist: SELESAI KNs eligible for / in external-lawyer assignment (branch-scoped). */
    public function listQuery(?User $user, array $filters): Builder
    {
        $branchId = $this->branchFilter($user);

        return KhidmatNasihat::query()
            ->with(['cawangan', 'kategori', 'peguamPanel'])
            ->where('status_kn', KhidmatNasihat::STATUS_SELESAI)
            // W3 (D2 dual-branch): origin keeps a transferred KN via cawangan_asal_id.
            ->when($branchId !== null, fn ($w) => $w->where(fn ($b) => $b
                ->where('cawangan_id', $branchId)
                ->orWhere('cawangan_asal_id', $branchId)))
            ->when($filters['status_agihan_pl'] ?? null, fn ($w, $v) => $w->where('status_agihan_pl', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($q) => $q
                ->where('no_permohonan', 'like', "%{$v}%")
                ->orWhere('nama_mangsa', 'like', "%{$v}%")))
            ->orderByDesc('id');
    }

    /** Open-grab pool for panel lawyers (all branches - lawyers are not branch-scoped). */
    public function grabPool(): Builder
    {
        return KhidmatNasihat::query()
            ->with(['cawangan', 'kategori'])
            ->where('status_agihan_pl', KhidmatNasihat::PL_BUKA_GRAB)
            ->orderBy('tarikh_buka_grab');
    }

    /** Resolve the cawangan_id a staff user is limited to, or null for all branches. */
    public function branchFilter(?User $user): ?int
    {
        if ($user === null || ! $user->isStaff() || ! filled($user->cawangan) || $user->can('cawangan.view-all')) {
            return null;
        }

        return Cawangan::where('nama', $user->cawangan)->value('id');
    }

    /** Guard: a KN may enter/refresh external-lawyer assignment only when SELESAI and not already DIAGIH. */
    private function assertBolehAgih(KhidmatNasihat $kn): void
    {
        if ($kn->status_kn !== KhidmatNasihat::STATUS_SELESAI) {
            throw new RuntimeException('Hanya Khidmat Nasihat berstatus SELESAI boleh diagihkan kepada peguam panel luar.');
        }

        if ($kn->status_agihan_pl === KhidmatNasihat::PL_DIAGIH) {
            throw new RuntimeException('KN ini telah diagihkan kepada peguam panel.');
        }
    }

    /** Guard: never route fresh work (assign/grab) to a deactivated panel lawyer. */
    private function assertPeguamAktif(PeguamPanel $peguam): void
    {
        if (! $peguam->isAktif()) {
            throw new RuntimeException('Peguam panel ini tidak aktif dan tidak boleh menerima kes baharu.');
        }
    }

    /** Guard: a branch-pinned officer may only act on a KN in their own branch (KN has no CawanganScope). */
    private function assertBranch(KhidmatNasihat $kn, User $actor): void
    {
        $branchId = $this->branchFilter($actor);

        if ($branchId !== null && (int) $kn->cawangan_id !== $branchId) {
            throw new RuntimeException('KN ini bukan di bawah cawangan anda.');
        }
    }
}
