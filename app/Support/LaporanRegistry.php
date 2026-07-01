<?php

namespace App\Support;

use App\Models\Form;
use App\Models\Scopes\CawanganScope;
use Illuminate\Database\Eloquent\Builder;

/**
 * W20 - shared report registry over the forms spine, used by both the interactive
 * LaporanController (sync CSV/PDF) and the queued ExportLaporanJob (bulk .xlsx). Keeping the
 * definitions + query builder in one place stops the controller and the job drifting apart.
 *
 * Pengantaraan reports carry the mediation officer + outcome columns (previously hollow).
 */
class LaporanRegistry
{
    /** Report registry: key => [label, group, filter, columns]. */
    public static function all(): array
    {
        $base = ['no_fail' => 'No. Fail', 'nama' => 'Pemohon', 'nokp' => 'No. KP', 'kategori_kes' => 'Kategori', 'cawangan' => 'Cawangan'];

        return [
            'permohonan' => [
                'label' => 'Laporan Permohonan', 'group' => 'Litigasi',
                'filter' => null,
                'columns' => $base + ['status' => 'Status', 'tarikh_permohonan' => 'Tarikh Mohon'],
            ],
            'pendaftaran-fail' => [
                'label' => 'Pendaftaran Fail', 'group' => 'Litigasi',
                'filter' => fn (Builder $q) => $q->whereNotNull('no_fail')->where('no_fail', '!=', ''),
                'columns' => $base + ['nama_pegawai' => 'Pegawai', 'tarikh_daftar' => 'Tarikh Daftar'],
            ],
            'status-fail' => [
                'label' => 'Status Fail', 'group' => 'Litigasi',
                'filter' => null,
                'columns' => $base + ['status' => 'Status', 'tarikh_tutup_fail' => 'Tarikh Tutup'],
            ],
            'penugasan-pengantaraan' => [
                'label' => 'Penugasan Pengantaraan', 'group' => 'Pengantaraan',
                'filter' => fn (Builder $q) => $q->whereNotNull('status_pengantaraan')->where('status_pengantaraan', '!=', ''),
                'columns' => $base + ['nama_pegawai' => 'Pengantara', 'tarikh_penugasan' => 'Tarikh Penugasan', 'tarikh_sidang' => 'Tarikh Sidang', 'status_pengantaraan' => 'Status'],
            ],
            'pencapaian-pengantaraan' => [
                'label' => 'Pencapaian Pengantaraan', 'group' => 'Pengantaraan',
                'filter' => fn (Builder $q) => $q->whereNotNull('cara_selesai')->where('cara_selesai', '!=', ''),
                'columns' => $base + ['nama_pegawai' => 'Pengantara', 'status_pengantaraan' => 'Status', 'cara_selesai' => 'Cara Selesai', 'tarikh_selesai' => 'Tarikh Selesai'],
            ],
            'tidak-dirujuk' => [
                'label' => 'Tidak Dirujuk Pengantaraan', 'group' => 'Pengantaraan',
                'filter' => fn (Builder $q) => $q->where(fn ($w) => $w->whereNull('status_pengantaraan')->orWhere('status_pengantaraan', '')),
                'columns' => $base + ['status' => 'Status', 'tarikh_permohonan' => 'Tarikh Mohon'],
            ],
        ];
    }

    public static function find(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function types(): array
    {
        return array_keys(self::all());
    }

    /**
     * Build the filtered forms query for a report. $filters accepts cawangan / dari / hingga.
     * Pass $bypassScope = true from queue context (no auth user → CawanganScope is a no-op
     * anyway, so the caller must supply an explicit `cawangan` filter for branch isolation).
     */
    public static function buildQuery(array $report, array $filters, bool $bypassScope = false): Builder
    {
        $query = Form::query();

        if ($bypassScope) {
            $query->withoutGlobalScope(CawanganScope::class);
        }

        return $query
            ->when($report['filter'] ?? null, fn ($q) => tap($q, $report['filter']))
            ->when($filters['cawangan'] ?? null, fn ($q, $v) => $q->where('cawangan', $v))
            ->when($filters['dari'] ?? null, fn ($q, $v) => $q->whereDate('tarikh_permohonan', '>=', $v))
            ->when($filters['hingga'] ?? null, fn ($q, $v) => $q->whereDate('tarikh_permohonan', '<=', $v))
            ->orderByDesc('id');
    }
}
