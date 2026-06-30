<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\PeguamPanel;
use App\Support\PeguamShortlistService;
use App\Support\StatusAgihan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * W11 — workload-ranked lawyer shortlist. Live mysql; TAG rows self-clean.
 */
class PeguamShortlistTest extends TestCase
{
    private const TAG = 'PHPUNITSL';

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        Form::where('cawangan', self::TAG)->delete();
        PeguamPanel::where('nama_peguam', 'like', self::TAG.'%')->delete();
    }

    private function makePeguam(string $suffix, string $kp, string $aktif = PeguamPanel::AKTIF): PeguamPanel
    {
        return PeguamPanel::create([
            'nama_peguam' => self::TAG.' '.$suffix, 'kp_peguam' => $kp, 'tel_peguam' => '0123456789',
            'emel_peguam' => strtolower($kp).'@firma.my', 'nama_firma' => 'Firma '.$suffix,
            'alamat_firma_1' => 'A1', 'alamat_firma_2' => 'A2', 'poskod_firma' => '40000',
            'negeri_firma' => 'Selangor', 'tel_firma' => '0312345678', 'statusAktif' => $aktif,
        ]);
    }

    private function assignCase(string $namaPeguam, string $status): void
    {
        Form::create([
            'nama' => self::TAG.' Kes', 'cawangan' => self::TAG, 'diterima' => '', 'created_at' => now(),
            'nama_pegawai_yang_dapat_kes' => $namaPeguam, 'status_agihan' => $status,
        ]);
    }

    public function test_shortlist_ranks_least_loaded_first(): void
    {
        $busy = $this->makePeguam('Busy', 'SLKP1');
        $free = $this->makePeguam('Free', 'SLKP2');

        // Busy has 2 open cases; Free has none.
        $this->assignCase($busy->nama_peguam, StatusAgihan::DITERIMA);
        $this->assignCase($busy->nama_peguam, StatusAgihan::DITAWARKAN);

        $shortlist = app(PeguamShortlistService::class)->shortlist(['limit' => 50])
            ->whereIn('nama', [$busy->nama_peguam, $free->nama_peguam])->values();

        $this->assertSame($free->nama_peguam, $shortlist->first()['nama'], 'least-loaded lawyer ranks first');
        $this->assertSame(0, $shortlist->first()['beban']);
        $this->assertSame(2, $shortlist->last()['beban']);
    }

    public function test_closed_cases_do_not_weigh_caseload(): void
    {
        $p = $this->makePeguam('Closed', 'SLKP3');
        $this->assignCase($p->nama_peguam, StatusAgihan::SELESAI); // closed — should not count

        $beban = app(PeguamShortlistService::class)->bebanByNama();
        $this->assertSame(0, (int) ($beban[$p->nama_peguam] ?? 0));
    }

    public function test_inactive_lawyer_excluded_from_shortlist(): void
    {
        $inactive = $this->makePeguam('Inactive', 'SLKP4', PeguamPanel::TIDAK_AKTIF);

        $names = app(PeguamShortlistService::class)->shortlist(['limit' => 100])->pluck('nama');
        $this->assertFalse($names->contains($inactive->nama_peguam));
    }
}
