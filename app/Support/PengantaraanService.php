<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cawangan;
use App\Models\Form;
use App\Models\LejarTuntutanBayaran;
use App\Models\Scopes\CawanganScope;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 mediation engine (W18 intake + W19 mediator assignment).
 *
 * A mediation is a tagged `forms` row (D11): TERUS = registered directly here,
 * LITIGASI = spun out of an existing case. Either way it owns a no_pengantaraan
 * (W17). Assigning a mediator (W19) sets the numeric officer link + a MEDIASI
 * claim-ledger row (W15). Transitions follow the AgihanService lock+transaction
 * discipline; the underlying forms row is read without CawanganScope so a
 * cross-branch / scheduler actor never loses it mid-write.
 */
class PengantaraanService
{
    public const SUMBER_TERUS = 'TERUS';

    public const SUMBER_LITIGASI = 'LITIGASI';

    public function __construct(private readonly NoFailGenerator $noFail) {}

    /** W18 — standalone (TERUS) intake: a tagged forms row with its own no_pengantaraan. */
    public function daftarTerus(array $attrs, User $actor): Form
    {
        return DB::transaction(function () use ($attrs, $actor): Form {
            $form = Form::create($attrs + [
                'sumber_pengantaraan' => self::SUMBER_TERUS,
                'status_pengantaraan' => $attrs['status_pengantaraan'] ?? 'Baru',
                'created_at' => now(),
                'tarikh_daftar' => now()->toDateString(),
                'tarikh_permohonan' => $attrs['tarikh_permohonan'] ?? now()->toDateString(),
                'didaftarkan_oleh' => $actor->name,
                'diterima' => '', // NOT NULL in legacy schema
            ]);

            $form->update(['no_pengantaraan' => $this->noFail->generatePengantaraan($form)]);

            Audit::log('forms', $form->id, Audit::INSERT,
                "Pendaftaran pengantaraan terus: {$form->no_pengantaraan} ({$form->nama}).", $actor->name);

            return $form;
        });
    }

    /** W18 (litigation path) — tag an existing case as a LITIGASI mediation + own number. */
    public function tandakanLitigasi(Form $kes, User $actor): void
    {
        if (filled($kes->no_pengantaraan)) {
            return;
        }

        DB::transaction(function () use ($kes) {
            $fresh = Form::withoutGlobalScope(CawanganScope::class)->whereKey($kes->id)->lockForUpdate()->firstOrFail();
            if (filled($fresh->no_pengantaraan)) {
                return;
            }
            $fresh->update([
                'sumber_pengantaraan' => self::SUMBER_LITIGASI,
                'no_pengantaraan' => $this->noFail->generatePengantaraan($fresh),
            ]);
        });

        Audit::log('forms', $kes->id, Audit::UPDATE,
            "Pengantaraan dibuka dari litigasi — no {$kes->fresh()->no_pengantaraan}.", $actor->name);
    }

    /** W19 — assign a mediator (staff officer) + write a MEDIASI claim-ledger row. */
    public function agihPengantara(Form $kes, int $officerId, User $actor): void
    {
        $officer = User::find($officerId);
        abort_if($officer === null, 422, 'Pegawai pengantara tidak sah.');

        DB::transaction(function () use ($kes, $officer, $actor) {
            $fresh = Form::withoutGlobalScope(CawanganScope::class)->whereKey($kes->id)->lockForUpdate()->firstOrFail();

            // PROC-03: don't silently displace an already-assigned mediator (double-click, or a
            // second officer). Re-assigning the SAME officer is a no-op; switching to a different
            // one must go through an explicit cancel first.
            abort_if(
                filled($fresh->id_pegawai_pengantara) && (int) $fresh->id_pegawai_pengantara !== $officer->id,
                422,
                'Kes ini telah mempunyai pegawai pengantara. Batalkan agihan sedia ada dahulu.'
            );

            // A mediation must own a number before a mediator is assigned (covers a
            // case that reached assignment without one).
            $noPengantaraan = filled($fresh->no_pengantaraan)
                ? $fresh->no_pengantaraan
                : $this->noFail->generatePengantaraan($fresh);

            $fresh->update([
                'id_pegawai_pengantara' => $officer->id,
                'nama_pegawai_pengantara' => $officer->name,
                'tarikh_agih_pengantara' => now()->toDateString(),
                'no_pengantaraan' => $noPengantaraan,
                'sumber_pengantaraan' => $fresh->sumber_pengantaraan ?? self::SUMBER_LITIGASI,
            ]);

            $this->ledgerRow($fresh, $actor);
        });

        Audit::log('forms', $kes->id, Audit::UPDATE,
            "Pengantaraan diagih kepada pegawai {$officer->name}.", $actor->name);
    }

    /**
     * MEDIASI claim-ledger row, idempotent per case. Guarded by Schema::hasTable so a
     * deploy missing the W15 ledger never blocks a mediator assignment.
     */
    private function ledgerRow(Form $kes, User $actor): void
    {
        if (! Schema::hasTable('lejar_tuntutan_bayaran')) {
            return;
        }

        $exists = LejarTuntutanBayaran::where('sumber', LejarTuntutanBayaran::SUMBER_MEDIASI)
            ->where('sumber_id', $kes->id)
            ->exists();

        if ($exists) {
            return;
        }

        app(LejarTuntutanService::class)->cipta([
            'sumber' => LejarTuntutanBayaran::SUMBER_MEDIASI,
            'sumber_id' => $kes->id,
            'id_kes' => $kes->id,
            'cawangan' => $kes->cawangan,
            'cawangan_id' => Cawangan::where('nama', $kes->cawangan)->value('id'),
            'jenis_tuntutan' => 'Bayaran Khidmat Pengantaraan',
            'jumlah_tuntutan' => 0,
            'status_tuntutan' => LejarTuntutanBayaran::STATUS_DRAF,
        ], $actor->name);
    }
}
