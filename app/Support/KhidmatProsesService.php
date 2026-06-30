<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cawangan;
use App\Models\Form;
use App\Models\KhidmatNasihat;
use App\Models\RefKategoriKn;
use App\Models\TemuJanji;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Khidmat Nasihat officer processing — batch 11 slices A+B.
 *
 * Slice A: the branch-scoped officer worklist query + dashboard count tiles.
 * Slice B: assign PKN officer (status_kn BAHARU->DALAM_PROSES) and the linked
 * temu_janji lifecycle transitions (accept / reject / attendance / complete).
 *
 * Every status write uses an explicit allowed-transition guard and throws a
 * {@see RuntimeException} on an invalid transition; the controller converts that
 * into a redirect-with-error (422-equivalent for this web surface). Writes that
 * touch a row read earlier are wrapped in DB::transaction with lockForUpdate to
 * close the check-then-write race.
 *
 * KhidmatNasihat has no CawanganScope (it uses cawangan_id, not the legacy
 * `cawangan` string column), so branch isolation is applied here explicitly:
 * a staff officer pinned to a branch (and without cawangan.view-all) is limited
 * to that branch's cawangan_id; view-all / no-branch officers see everything.
 */
class KhidmatProsesService
{
    /** Statuses surfaced as dashboard count tiles. */
    public const DASHBOARD_STATUSES = [
        KhidmatNasihat::STATUS_BAHARU,
        KhidmatNasihat::STATUS_DALAM_PROSES,
        KhidmatNasihat::STATUS_SELESAI,
    ];

    /** Allowed temu_janji.status transitions, keyed by action. */
    private const TEMU_TRANSITIONS = [
        'terima' => ['from' => ['MENUNGGU'], 'to' => 'DISAHKAN'],
        'tolak' => ['from' => ['MENUNGGU'], 'to' => 'BATAL'],
        'hadir' => ['from' => ['DISAHKAN'], 'to' => 'HADIR'],
        'tidakHadir' => ['from' => ['DISAHKAN'], 'to' => 'TIDAK_HADIR'],
        'selesai' => ['from' => ['HADIR'], 'to' => 'SELESAI'],
    ];

    /**
     * Resolve the cawangan_id a user is limited to, or null for all branches.
     * Staff pinned to a branch string (without cawangan.view-all) map to that
     * branch's id; everyone else (view-all, no branch, lawyers) sees everything.
     */
    public function branchFilter(?User $user): ?int
    {
        if ($user === null || ! $user->isStaff() || ! filled($user->cawangan) || $user->can('cawangan.view-all')) {
            return null;
        }

        return Cawangan::where('nama', $user->cawangan)->value('id');
    }

    /**
     * Officer worklist query (branch-scoped + filtered). Caller paginates.
     *
     * @param  array{status_kn?:string,id_pegawai_kn?:int|string,id_kategori?:int|string,dari?:string,hingga?:string,q?:string}  $filters
     */
    public function listQuery(?User $user, array $filters): Builder
    {
        $branchId = $this->branchFilter($user);

        return KhidmatNasihat::query()
            ->with(['cawangan', 'kategori', 'pegawaiKn', 'temuJanji'])
            ->when($branchId !== null, fn ($w) => $w->where('cawangan_id', $branchId))
            ->when($filters['status_kn'] ?? null, fn ($w, $v) => $w->where('status_kn', $v))
            ->when($filters['id_pegawai_kn'] ?? null, fn ($w, $v) => $w->where('id_pegawai_kn', $v))
            ->when($filters['id_kategori'] ?? null, fn ($w, $v) => $w->where('id_kategori', $v))
            ->when($filters['dari'] ?? null, fn ($w, $v) => $w->whereDate('created_at', '>=', $v))
            ->when($filters['hingga'] ?? null, fn ($w, $v) => $w->whereDate('created_at', '<=', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($q) => $q
                ->where('no_permohonan', 'like', "%{$v}%")
                ->orWhere('nama_mangsa', 'like', "%{$v}%")))
            ->orderByDesc('id');
    }

    /**
     * Status count tiles for one branch (or all when $branchId is null).
     *
     * @return array<string,int> status => count (every DASHBOARD_STATUSES key present)
     */
    public function dashboardCounts(?int $branchId): array
    {
        $rows = KhidmatNasihat::query()
            ->when($branchId !== null, fn ($w) => $w->where('cawangan_id', $branchId))
            ->whereIn('status_kn', self::DASHBOARD_STATUSES)
            ->selectRaw('status_kn, COUNT(*) as total')
            ->groupBy('status_kn')
            ->pluck('total', 'status_kn');

        $counts = [];
        foreach (self::DASHBOARD_STATUSES as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        return $counts;
    }

    /**
     * Assign an advisory officer (PKN) and move BAHARU -> DALAM_PROSES.
     * Fixes the legacy bug where CreateTemuJanji dropped IdPegawaiKN.
     *
     * @throws RuntimeException when the case is not in BAHARU.
     */
    public function assignPkn(KhidmatNasihat $khidmat, int $pegawaiId, string $actor): void
    {
        DB::transaction(function () use ($khidmat, $pegawaiId, $actor) {
            $fresh = KhidmatNasihat::whereKey($khidmat->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status_kn !== KhidmatNasihat::STATUS_BAHARU) {
                throw new RuntimeException('Hanya permohonan berstatus BAHARU boleh diagihkan kepada pegawai.');
            }

            $fresh->update([
                'id_pegawai_kn' => $pegawaiId,
                'tarikh_proses' => now(),
                'status_kn' => KhidmatNasihat::STATUS_DALAM_PROSES,
                'kemaskini_oleh' => $actor,
            ]);
        });

        Audit::log('khidmat_nasihat', $khidmat->id, Audit::UPDATE,
            "Agihan Pegawai Khidmat Nasihat (#{$pegawaiId}) — status DALAM_PROSES.", $actor);
    }

    /** Accept the linked appointment: MENUNGGU -> DISAHKAN. */
    public function terima(KhidmatNasihat $khidmat, string $actor): void
    {
        $this->transitionTemu($khidmat, 'terima', $actor);
    }

    /** Reject the linked appointment: MENUNGGU -> BATAL, recording the reason. */
    public function tolak(KhidmatNasihat $khidmat, ?string $ulasan, string $actor): void
    {
        $this->transitionTemu($khidmat, 'tolak', $actor, function (KhidmatNasihat $kn) use ($ulasan) {
            $kn->update(['ulasan_pegawai' => $ulasan, 'kemaskini_oleh' => $kn->kemaskini_oleh]);
        });
    }

    /** Mark attendance: DISAHKAN -> HADIR | TIDAK_HADIR. */
    public function kehadiran(KhidmatNasihat $khidmat, bool $hadir, string $actor): void
    {
        $this->transitionTemu($khidmat, $hadir ? 'hadir' : 'tidakHadir', $actor);
    }

    /** Complete: appointment HADIR -> SELESAI and khidmat_nasihat -> SELESAI. */
    public function selesai(KhidmatNasihat $khidmat, string $actor): void
    {
        $this->transitionTemu($khidmat, 'selesai', $actor, function (KhidmatNasihat $kn) use ($actor) {
            $kn->update(['status_kn' => KhidmatNasihat::STATUS_SELESAI, 'kemaskini_oleh' => $actor]);
        });
    }

    /**
     * Buka Kes — slice C: open a litigation case (a forms row) from a completed
     * Khidmat Nasihat. Manual officer action (not auto-on-attendance).
     *
     * Guards:
     *   - the KN must be SELESAI (appointment attended + completed);
     *   - it must not already be linked to a case (id_forms === null) — no second row.
     *
     * Prefill mirrors KesController::store: created_at/tarikh_daftar/didaftarkan_oleh
     * and diterima='' (NOT NULL legacy col), then no_fail via NoFailGenerator when blank.
     * The branch string lands in forms.cawangan so CawanganScope lines up.
     *
     * @throws RuntimeException when the KN is not SELESAI or a case already exists.
     */
    public function bukaKes(KhidmatNasihat $kn, User $actor): Form
    {
        return DB::transaction(function () use ($kn, $actor): Form {
            $fresh = KhidmatNasihat::whereKey($kn->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status_kn !== KhidmatNasihat::STATUS_SELESAI) {
                throw new RuntimeException('KN belum selesai — kes hanya boleh dibuka selepas khidmat nasihat selesai.');
            }

            if ($fresh->id_forms !== null) {
                throw new RuntimeException('Kes telah dibuka untuk permohonan ini.');
            }

            $cawangan = Cawangan::find($fresh->cawangan_id)?->nama ?? $actor->cawangan;
            $tarikhKn = $fresh->temuJanji?->tarikh_temu_janji;
            $kategori = $fresh->id_kategori ? RefKategoriKn::find($fresh->id_kategori)?->jenis_kategori : null;

            $form = Form::create([
                'nama' => $fresh->nama_mangsa,
                'nokp' => $fresh->id_pengenalan_mangsa,
                'jenis_kes' => $fresh->jenis_kes,
                'kategori_kes' => $kategori,
                'tarikh_khidmat_nasihat' => $tarikhKn,
                'cawangan' => $cawangan,
                'created_at' => now(),
                'tarikh_daftar' => now()->toDateString(),
                'didaftarkan_oleh' => $actor->name,
                'diterima' => '', // NOT NULL in legacy schema
            ]);

            if (blank($form->no_fail)) {
                $form->update(['no_fail' => app(NoFailGenerator::class)->generate($form)]);
            }

            $fresh->id_forms = $form->id;
            $fresh->save();

            Audit::log('khidmat_nasihat', $fresh->id, Audit::UPDATE,
                "Buka Kes — forms #{$form->id} (No. Fail: {$form->no_fail}).", $actor->name);

            return $form;
        });
    }

    /**
     * Apply one guarded temu_janji transition under a row lock. The optional
     * $afterKn callback runs inside the same transaction to keep the KN record
     * and its appointment consistent (e.g. reason on reject, SELESAI on complete).
     *
     * @throws RuntimeException when no appointment exists or the transition is illegal.
     */
    private function transitionTemu(KhidmatNasihat $khidmat, string $action, string $actor, ?callable $afterKn = null): void
    {
        $rule = self::TEMU_TRANSITIONS[$action];

        DB::transaction(function () use ($khidmat, $rule, $afterKn) {
            $temu = $khidmat->id_temu_janji
                ? TemuJanji::whereKey($khidmat->id_temu_janji)->lockForUpdate()->first()
                : null;

            if ($temu === null) {
                throw new RuntimeException('Tiada janji temu berkaitan untuk permohonan ini.');
            }

            if (! in_array($temu->status, $rule['from'], true)) {
                throw new RuntimeException(
                    "Peralihan status janji temu tidak sah (dari {$temu->status} ke {$rule['to']})."
                );
            }

            $temu->update(['status' => $rule['to']]);

            if ($afterKn !== null) {
                $afterKn($khidmat);
            }
        });

        Audit::log('temu_janji', (int) $khidmat->id_temu_janji, Audit::UPDATE,
            "Janji temu KN #{$khidmat->id}: status -> {$rule['to']}.", $actor);
    }
}
