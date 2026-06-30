<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\KhidmatNasihat;
use App\Models\MaklumBalas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Batch 12 slice 1 — Maklum Balas (public satisfaction feedback).
 * Live mysql per repo convention; PHPUNIT-tagged rows cleaned up. Route is
 * PUBLIC (no auth) — no actingAs.
 */
class Batch12MaklumBalasTest extends TestCase
{
    private const TAG = 'PHPUNIT';

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
        KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')->delete(); // cascades to maklum_balas
    }

    private function makeKn(string $status = KhidmatNasihat::STATUS_SELESAI): KhidmatNasihat
    {
        return KhidmatNasihat::create([
            'no_permohonan' => self::TAG.'-MB-'.uniqid(),
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => self::TAG.' Mangsa',
            'status_kn' => $status,
        ]);
    }

    /** Valid payload for a SELESAI KN submission. */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'soalan_1a' => '1',
            'soalan_2a' => 'BAIK',
            'soalan_cadangan' => self::TAG.' lebih banyak slot.',
        ], $overrides);
    }

    // ---- Store happy path ----

    public function test_store_creates_feedback_for_selesai_kn(): void
    {
        $kn = $this->makeKn();

        $this->post(route('maklum-balas.store', $kn->no_permohonan), $this->validPayload())
            ->assertRedirect(route('maklum-balas.show', $kn->no_permohonan))
            ->assertSessionHas('maklum_balas_berjaya');

        $this->assertDatabaseHas('maklum_balas', [
            'khidmat_nasihat_id' => $kn->id,
            'soalan_1a' => 1,
            'soalan_2a' => 'BAIK',
        ]);
        $this->assertSame(1, MaklumBalas::where('khidmat_nasihat_id', $kn->id)->count());

        $row = MaklumBalas::where('khidmat_nasihat_id', $kn->id)->firstOrFail();
        $this->assertNotNull($row->dihantar_dari_ip); // request IP captured
    }

    // ---- One feedback per KN (unique guard) ----

    public function test_second_submission_is_blocked_no_duplicate_row(): void
    {
        $kn = $this->makeKn();

        $this->post(route('maklum-balas.store', $kn->no_permohonan), $this->validPayload())->assertRedirect();
        // Second submission — app guard short-circuits, DB unique index backstops.
        $this->post(route('maklum-balas.store', $kn->no_permohonan), $this->validPayload(['soalan_2a' => 'CEMERLANG']))
            ->assertRedirect(route('maklum-balas.show', $kn->no_permohonan));

        $this->assertSame(1, MaklumBalas::where('khidmat_nasihat_id', $kn->id)->count());
        // Original answer preserved (second write did not overwrite).
        $this->assertSame('BAIK', MaklumBalas::where('khidmat_nasihat_id', $kn->id)->value('soalan_2a'));
    }

    // ---- Validation ----

    public function test_validation_rejects_empty_soalan_1_and_missing_soalan_2a(): void
    {
        $kn = $this->makeKn();

        $this->post(route('maklum-balas.store', $kn->no_permohonan), [
            'soalan_cadangan' => self::TAG.' nothing selected',
        ])->assertSessionHasErrors(['soalan_1a', 'soalan_2a']);

        $this->assertSame(0, MaklumBalas::where('khidmat_nasihat_id', $kn->id)->count());
    }

    public function test_validation_requires_lain_lain_text_when_1e_checked(): void
    {
        $kn = $this->makeKn();

        $this->post(route('maklum-balas.store', $kn->no_permohonan), [
            'soalan_1e' => '1',
            'soalan_2a' => 'BAIK',
        ])->assertSessionHasErrors(['soalan_1_lain_lain']);

        $this->assertSame(0, MaklumBalas::where('khidmat_nasihat_id', $kn->id)->count());
    }

    // ---- Show states ----

    public function test_show_on_non_selesai_kn_shows_belum_tersedia_no_form(): void
    {
        $kn = $this->makeKn(KhidmatNasihat::STATUS_DALAM_PROSES);

        $this->get(route('maklum-balas.show', $kn->no_permohonan))
            ->assertOk()
            ->assertSee('Belum Tersedia')
            ->assertDontSee('Hantar Maklum Balas');
    }

    public function test_show_on_selesai_kn_renders_form(): void
    {
        $kn = $this->makeKn();

        $this->get(route('maklum-balas.show', $kn->no_permohonan))
            ->assertOk()
            ->assertSee('Hantar Maklum Balas')
            ->assertSee($kn->no_permohonan);
    }

    public function test_show_on_already_submitted_kn_shows_thank_you_no_form(): void
    {
        $kn = $this->makeKn();
        MaklumBalas::create([
            'khidmat_nasihat_id' => $kn->id,
            'soalan_1a' => true,
            'soalan_2a' => 'BAIK',
        ]);

        $this->get(route('maklum-balas.show', $kn->no_permohonan))
            ->assertOk()
            ->assertSee('Terima Kasih')
            ->assertDontSee('Hantar Maklum Balas');
    }

    public function test_show_on_unknown_no_permohonan_404s(): void
    {
        $this->get(route('maklum-balas.show', self::TAG.'-DOES-NOT-EXIST'))->assertNotFound();
    }

    // ---- Throttle middleware present ----

    public function test_routes_have_throttle_middleware(): void
    {
        foreach (['maklum-balas.show', 'maklum-balas.store'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "route {$name} missing");
            $this->assertContains('throttle:6,1', $route->gatherMiddleware());
        }
    }
}
