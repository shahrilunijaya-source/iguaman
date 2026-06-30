<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\LejarTuntutanBayaran;
use App\Models\PeguamPanel;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * W15 — central claim-ledger service. Holds no_tuntutan generation, guarded
 * lifecycle transitions, the KN auto-create bridge (D4), and branch-scoped
 * list/dashboard queries. Mirrors the guard + transaction style of
 * {@see KhidmatProsesService} / {@see AgihanService}.
 */
class LejarTuntutanService
{
    /** Statuses surfaced as dashboard tiles. */
    public const DASHBOARD_STATUSES = [
        LejarTuntutanBayaran::STATUS_DIHANTAR,
        LejarTuntutanBayaran::STATUS_SEMAKAN,
        LejarTuntutanBayaran::STATUS_DILULUS,
        LejarTuntutanBayaran::STATUS_DIBAYAR,
    ];

    /** Allowed status_tuntutan transitions, keyed by action. */
    private const TRANSITIONS = [
        'hantar' => ['from' => ['DRAF'], 'to' => 'DIHANTAR'],
        'semak' => ['from' => ['DIHANTAR'], 'to' => 'SEMAKAN'],
        'lulus' => ['from' => ['SEMAKAN'], 'to' => 'DILULUS'],
        'tolak' => ['from' => ['DIHANTAR', 'SEMAKAN'], 'to' => 'DITOLAK'],
        'bayar' => ['from' => ['DILULUS'], 'to' => 'DIBAYAR'],
        'batal' => ['from' => ['DRAF', 'DIHANTAR', 'SEMAKAN'], 'to' => 'BATAL'],
    ];

    /**
     * Create a ledger row from any source. Wishes 5/9/19 call this with the right
     * `sumber`. Returns the created row.
     *
     * @param  array<string,mixed>  $attrs
     */
    public function cipta(array $attrs, string $actor): LejarTuntutanBayaran
    {
        return DB::transaction(function () use ($attrs, $actor): LejarTuntutanBayaran {
            $attrs['no_tuntutan'] ??= $this->generateNoTuntutan();
            $attrs['status_tuntutan'] ??= LejarTuntutanBayaran::STATUS_DRAF;
            $attrs['tarikh_tuntutan'] ??= now()->toDateString();
            $attrs['cipta_oleh'] ??= $actor;
            $attrs['kemaskini_oleh'] = $actor;

            $row = LejarTuntutanBayaran::create($attrs);

            Audit::log('lejar_tuntutan_bayaran', $row->id, Audit::INSERT,
                "Tuntutan {$row->no_tuntutan} dicipta (sumber {$row->sumber}).", $actor);

            return $row;
        });
    }

    /**
     * Auto-create a KN claim row when a paid advisory completes (D4 / G-M3).
     * Idempotent via the (sumber, id_khidmat_nasihat) unique key; returns the
     * existing or new row, or null when the KN is free (is_percuma) or has no fee.
     */
    public function fromKhidmatNasihat(KhidmatNasihat $kn, string $actor): ?LejarTuntutanBayaran
    {
        if ($kn->is_percuma || (float) $kn->jumlah_bayaran <= 0) {
            return null;
        }

        $existing = LejarTuntutanBayaran::where('sumber', LejarTuntutanBayaran::SUMBER_KN)
            ->where('id_khidmat_nasihat', $kn->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->cipta([
            'sumber' => LejarTuntutanBayaran::SUMBER_KN,
            'sumber_id' => $kn->id,
            'id_khidmat_nasihat' => $kn->id,
            'id_kes' => $kn->id_forms,
            'id_pengguna' => $kn->id_pengguna,
            'cawangan_id' => $kn->cawangan_id,
            'cawangan' => Cawangan::find($kn->cawangan_id)?->nama,
            'jenis_tuntutan' => 'Bayaran Khidmat Nasihat',
            'jumlah_tuntutan' => $kn->jumlah_bayaran,
            'status_tuntutan' => LejarTuntutanBayaran::STATUS_DIHANTAR,
        ], $actor);
    }

    /**
     * Seed a PEGUAM_LUAR claim row when a KN is assigned to an external panel lawyer (W5).
     * Idempotent via the (sumber, id_khidmat_nasihat) unique key — re-assigning the same KN
     * returns the existing row instead of throwing a duplicate-key error. The row starts as a
     * DRAF with no amount; the lawyer fills jumlah_tuntutan + submits via self-service tuntutan.
     */
    public function fromPeguamLuar(KhidmatNasihat $kn, PeguamPanel $peguam, string $actor): LejarTuntutanBayaran
    {
        $existing = LejarTuntutanBayaran::where('sumber', LejarTuntutanBayaran::SUMBER_PEGUAM_LUAR)
            ->where('id_khidmat_nasihat', $kn->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->cipta([
            'sumber' => LejarTuntutanBayaran::SUMBER_PEGUAM_LUAR,
            'sumber_id' => $peguam->id,
            'id_khidmat_nasihat' => $kn->id,
            'id_kes' => $kn->id_forms,
            'id_peguam_panel' => $peguam->id,
            'kp_peguam' => $peguam->kp_peguam,
            'id_pengguna' => $kn->id_pengguna,
            'cawangan_id' => $kn->cawangan_id,
            'cawangan' => Cawangan::find($kn->cawangan_id)?->nama,
            'jenis_tuntutan' => 'Bayaran Peguam Luar (Khidmat Nasihat)',
            'jumlah_tuntutan' => 0,
            'status_tuntutan' => LejarTuntutanBayaran::STATUS_DRAF,
        ], $actor);
    }

    /**
     * W2 — record a manual counter payment of a KN intake fee. Ensures the central
     * SUMBER_KN ledger row exists, stamps the receipt, marks it DIBAYAR, and flips the
     * KN payment flag. A counter payment IS the payment, so it skips the panel-claim
     * approval chain (DIHANTAR->SEMAKAN->DILULUS). Idempotent: re-recording overwrites
     * the receipt on the same row. Returns null when the KN has no fee to collect.
     *
     * @param  array{nombor_resit:string,tarikh_resit:string,kaedah_bayaran:string,rujukan_bayaran?:?string}  $receipt
     */
    public function rekodBayaranKn(KhidmatNasihat $kn, array $receipt, string $actor): ?LejarTuntutanBayaran
    {
        if ($kn->is_percuma || (float) $kn->jumlah_bayaran <= 0) {
            return null;
        }

        return DB::transaction(function () use ($kn, $receipt, $actor): LejarTuntutanBayaran {
            $row = $this->fromKhidmatNasihat($kn, $actor);

            if ($row === null) {
                throw new RuntimeException('Tiada baris lejar untuk Khidmat Nasihat ini.');
            }

            $fresh = LejarTuntutanBayaran::whereKey($row->id)->lockForUpdate()->firstOrFail();
            $fresh->fill([
                'nombor_resit' => $receipt['nombor_resit'],
                'tarikh_resit' => $receipt['tarikh_resit'],
                'kaedah_bayaran' => $receipt['kaedah_bayaran'],
                'rujukan_bayaran' => $receipt['rujukan_bayaran'] ?? null,
                'jumlah_bayaran' => $kn->jumlah_bayaran,
                'status_bayaran' => true,
                'status_tuntutan' => LejarTuntutanBayaran::STATUS_DIBAYAR,
                'kemaskini_oleh' => $actor,
            ]);
            $fresh->save();

            KhidmatNasihat::whereKey($kn->id)->update(['status_bayaran' => true]);

            Audit::log('lejar_tuntutan_bayaran', $fresh->id, Audit::UPDATE,
                "Bayaran kaunter KN direkod (resit {$receipt['nombor_resit']}).", $actor);

            return $fresh;
        });
    }

    /** Apply one guarded lifecycle transition under a row lock. */
    public function transition(LejarTuntutanBayaran $row, string $action, string $actor, array $extra = []): void
    {
        if (! isset(self::TRANSITIONS[$action])) {
            throw new RuntimeException("Tindakan tuntutan tidak dikenali: {$action}.");
        }

        $rule = self::TRANSITIONS[$action];

        DB::transaction(function () use ($row, $rule, $extra) {
            $fresh = LejarTuntutanBayaran::whereKey($row->id)->lockForUpdate()->firstOrFail();

            if (! in_array($fresh->status_tuntutan, $rule['from'], true)) {
                throw new RuntimeException(
                    "Peralihan status tuntutan tidak sah (dari {$fresh->status_tuntutan} ke {$rule['to']})."
                );
            }

            $fresh->fill($extra);
            $fresh->status_tuntutan = $rule['to'];
            $fresh->save();

            // bayar: flip the linked KN payment flag too (closes the receipt gap).
            if ($rule['to'] === LejarTuntutanBayaran::STATUS_DIBAYAR && $fresh->id_khidmat_nasihat) {
                KhidmatNasihat::whereKey($fresh->id_khidmat_nasihat)->update(['status_bayaran' => true]);
            }
        });

        $audit = $action === 'lulus' ? Audit::APPROVE : ($action === 'tolak' ? Audit::REJECT : Audit::UPDATE);
        Audit::log('lejar_tuntutan_bayaran', $row->id, $audit,
            "Tuntutan {$row->no_tuntutan}: status -> {$rule['to']}.", $actor);
    }

    /** TNT-{year}-{seq} sequence, locked per year prefix. */
    public function generateNoTuntutan(): string
    {
        $prefix = 'TNT-'.now()->year.'-';

        $last = LejarTuntutanBayaran::where('no_tuntutan', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('no_tuntutan')
            ->value('no_tuntutan');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** Resolve the cawangan_id a user is limited to, or null for all branches (D5). */
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
     * @param  array{status_tuntutan?:string,sumber?:string,q?:string}  $filters
     */
    public function listQuery(?User $user, array $filters): Builder
    {
        $branchId = $this->branchFilter($user);

        return LejarTuntutanBayaran::query()
            ->with(['form', 'peguam', 'khidmatNasihat'])
            ->when($branchId !== null, fn ($w) => $w->where('cawangan_id', $branchId))
            ->when($filters['status_tuntutan'] ?? null, fn ($w, $v) => $w->where('status_tuntutan', $v))
            ->when($filters['sumber'] ?? null, fn ($w, $v) => $w->where('sumber', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($q) => $q
                ->where('no_tuntutan', 'like', "%{$v}%")
                ->orWhere('kp_peguam', 'like', "%{$v}%")))
            ->orderByDesc('id');
    }

    /**
     * Status count tiles for one branch (or all when $branchId is null).
     *
     * @return array<string,int>
     */
    public function dashboardCounts(?int $branchId): array
    {
        $rows = LejarTuntutanBayaran::query()
            ->when($branchId !== null, fn ($w) => $w->where('cawangan_id', $branchId))
            ->whereIn('status_tuntutan', self::DASHBOARD_STATUSES)
            ->selectRaw('status_tuntutan, COUNT(*) as total')
            ->groupBy('status_tuntutan')
            ->pluck('total', 'status_tuntutan');

        $counts = [];
        foreach (self::DASHBOARD_STATUSES as $status) {
            $counts[$status] = (int) ($rows[$status] ?? 0);
        }

        return $counts;
    }
}
