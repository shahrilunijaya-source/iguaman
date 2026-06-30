<?php

namespace Tests\Feature;

use App\Models\ButiranPeguamPanel2;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * G-H3 — public panel-application status lookup (legacy semak.php parity). No login.
 * Reveals only the status label + apply date (+ rejection reason); never credentials.
 * Live mysql per repo convention; TAG rows self-clean.
 */
class PeguamSemakStatusTest extends TestCase
{
    private const TAG = 'SSCHK';

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
        ButiranPeguamPanel2::where('kpBaru', 'like', self::TAG.'%')->delete();
    }

    private function applicant(string $kp, string $status, array $extra = []): ButiranPeguamPanel2
    {
        return ButiranPeguamPanel2::create(array_merge([
            'namaPeguam' => self::TAG.' Pemohon',
            'kpBaru' => $kp,
            'jantina' => 'Lelaki',
            'noTelBimbit' => '0123456789',
            'emelPeguam' => strtolower($kp).'@firma.my',
            'kelulusanAkademik' => 'LLB',
            'tahunPengalaman' => '5',
            'tahunPengalamanSyarie' => '0',
            'bilanganKes' => '10',
            'keteranganKes' => 'N/A',
            'permohonan_status' => $status,
            'tarikhMohon' => now(),
        ], $extra));
    }

    public function test_form_loads_without_login(): void
    {
        $this->get(route('peguam.semak-status'))
            ->assertOk()
            ->assertSee('Semak Status Permohonan');
    }

    public function test_baharu_application_shows_processing(): void
    {
        $this->applicant(self::TAG.'01', '0');

        $this->post(route('peguam.semak-status.check'), ['kpBaru' => self::TAG.'01'])
            ->assertOk()
            ->assertSee('Baharu')
            ->assertSee('sedang diproses');
    }

    public function test_approved_application_does_not_leak_credentials(): void
    {
        $this->applicant(self::TAG.'02', '1');

        $this->post(route('peguam.semak-status.check'), ['kpBaru' => self::TAG.'02'])
            ->assertOk()
            ->assertSee('Lulus')
            ->assertDontSee('kata laluan'); // temp password is never exposed publicly
    }

    public function test_rejected_application_shows_reason(): void
    {
        $this->applicant(self::TAG.'03', '2', ['sebabTidakDiluluskan' => self::TAG.' dokumen tidak lengkap']);

        $this->post(route('peguam.semak-status.check'), ['kpBaru' => self::TAG.'03'])
            ->assertOk()
            ->assertSee('Tidak Lulus')
            ->assertSee('dokumen tidak lengkap');
    }

    public function test_unknown_ic_reports_not_found(): void
    {
        $this->post(route('peguam.semak-status.check'), ['kpBaru' => self::TAG.'99'])
            ->assertOk()
            ->assertSee('Tiada permohonan ditemui');
    }

    public function test_honeypot_blocks_submission(): void
    {
        $this->applicant(self::TAG.'04', '0');

        $this->post(route('peguam.semak-status.check'), ['kpBaru' => self::TAG.'04', 'website' => 'bot'])
            ->assertSessionHasErrors('website');
    }
}
