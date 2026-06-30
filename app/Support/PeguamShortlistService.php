<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Form;
use App\Models\PeguamPanel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * W11 — automated case-distribution support. Ranks panel lawyers by current open
 * caseload (least-loaded first) so PPUU picks from a workload-aware shortlist
 * instead of the full flat list. Reused by W5 (external-lawyer assignment).
 *
 * "Open caseload" = cases in the DITAWARKAN/DITERIMA buckets (offer outstanding or
 * actively handled), NOT closed/withdrawn — those should not weigh down a lawyer.
 */
class PeguamShortlistService
{
    /** Statuses that count as an open, weighing caseload. */
    private const OPEN_BUCKETS = [StatusAgihan::DITAWARKAN, StatusAgihan::DITERIMA];

    /**
     * Open-case counts keyed by lawyer name (the legacy join key on forms).
     * Single source of truth for the workload dashboard and the shortlist.
     *
     * @return Collection<string,int>
     */
    public function bebanByNama(): Collection
    {
        return Form::query()
            ->whereNotNull('nama_pegawai_yang_dapat_kes')
            ->where('nama_pegawai_yang_dapat_kes', '!=', '')
            ->whereIn('status_agihan', StatusAgihan::bucketValues(self::OPEN_BUCKETS))
            ->select('nama_pegawai_yang_dapat_kes', DB::raw('COUNT(*) as n'))
            ->groupBy('nama_pegawai_yang_dapat_kes')
            ->pluck('n', 'nama_pegawai_yang_dapat_kes');
    }

    /**
     * Workload-ranked shortlist of active panel lawyers (least-loaded first).
     *
     * @param  array{bidang?:string,limit?:int}  $opt  Optional practice-area code
     *         (matches butiran_peguam_panel_6.category) + result cap.
     * @return Collection<int,array{id:int,nama:string,kp:?string,firma:?string,beban:int}>
     */
    public function shortlist(array $opt = []): Collection
    {
        $beban = $this->bebanByNama();
        $limit = $opt['limit'] ?? 15;

        $query = PeguamPanel::query()
            // Active = statusAktif not explicitly '0' (null/blank treated active, per isAktif()).
            ->where(fn ($q) => $q->whereNull('statusAktif')->orWhere('statusAktif', '!=', PeguamPanel::TIDAK_AKTIF));

        // Optional practice-area filter via the specialisation table (collation-safe join).
        if (! empty($opt['bidang'])) {
            $query->whereExists(function ($sub) use ($opt) {
                $sub->select(DB::raw(1))
                    ->from('butiran_peguam_panel_6 as b6')
                    ->whereColumn('b6.kpBaru', DB::raw('peguam_panel.kp_peguam COLLATE utf8mb4_unicode_ci'))
                    ->where('b6.category', $opt['bidang']);
            });
        }

        return $query->orderBy('nama_peguam')->get(['id', 'nama_peguam', 'kp_peguam', 'nama_firma'])
            ->map(fn (PeguamPanel $p) => [
                'id' => $p->id,
                'nama' => $p->nama_peguam,
                'kp' => $p->kp_peguam,
                'firma' => $p->nama_firma,
                'beban' => (int) ($beban[$p->nama_peguam] ?? 0),
            ])
            ->sortBy([['beban', 'asc'], ['nama', 'asc']])
            ->values()
            ->take($limit);
    }
}
