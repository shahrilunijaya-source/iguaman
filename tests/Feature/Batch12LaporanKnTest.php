<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\MaklumBalas;
use App\Models\RefKategoriKesKn;
use App\Models\RefKategoriKn;
use App\Models\RefSubkategoriKn;
use App\Models\TemuJanji;
use App\Models\User;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 12 slice 2 — 8 statistical reports for Khidmat Nasihat.
 *
 * Covers: detail/aggregation correctness over seeded fixtures, month/year +
 * cawangan + kategori filters, explicit branch-scoping (KN has no CawanganScope),
 * maklum_balas bucket counts (reports 2 & 7), Excel export 200, laporan.view
 * gating. Live mysql per repo convention; PHPUNIT-tagged rows cleaned up.
 */
class Batch12LaporanKnTest extends TestCase
{
    private const TAG = 'PHPUNIT12L';

    private RefKategoriKn $kategoriA;

    private RefKategoriKn $kategoriB;

    private RefSubkategoriKn $subA;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => env('DB_DATABASE', 'iguaman_2in1')]);
        DB::purge('mysql');
        DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder)->run();
        (new Batch8MastersSeeder)->run();
        (new TestUsersSeeder)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->cleanup();

        $this->kategoriA = RefKategoriKn::create(['jenis_kategori' => self::TAG.' Kategori A', 'aktif' => true]);
        $this->kategoriB = RefKategoriKn::create(['jenis_kategori' => self::TAG.' Kategori B', 'aktif' => true]);
        // Subkategori needs the L2 parent chain (kategori -> kategori_kes -> subkategori).
        $kesA = RefKategoriKesKn::create(['kategori_id' => $this->kategoriA->id, 'nama' => self::TAG.' Kes A', 'aktif' => true]);
        $this->subA = RefSubkategoriKn::create(['kategori_kes_id' => $kesA->id, 'nama' => self::TAG.' Sub A', 'aktif' => true]);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        TemuJanji::where('cipta_oleh', 'like', self::TAG.'%')->delete();
        KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->delete(); // cascades to maklum_balas
        // kategori_kes + subkategori cascade-delete from their kategori parent.
        RefKategoriKn::where('jenis_kategori', 'like', self::TAG.'%')->delete();
        User::where('email', 'like', '%@b12l.local')->delete();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Branch-pinned officer with laporan.view, no cawangan.view-all (pegawai role). */
    private function officer(string $cawangan): User
    {
        $u = User::create([
            'name' => 'B12L Pegawai', 'email' => 'peg-'.uniqid().'@b12l.local',
            'password' => bcrypt('x'), 'user_type' => 'staff',
            'role' => 'pegawai', 'cawangan' => $cawangan, 'is_active' => true,
        ]);
        $u->syncRoles(['pegawai']);

        return $u;
    }

    private function branch(string $nama): Cawangan
    {
        return Cawangan::where('nama', $nama)->firstOrFail();
    }

    private function makeKn(array $attrs = []): KhidmatNasihat
    {
        return KhidmatNasihat::create(array_merge([
            'no_permohonan' => self::TAG.'-'.uniqid(),
            'jenis_permohonan' => 'DIRI_SENDIRI',
            'nama_mangsa' => self::TAG.' Mangsa',
            'status_kn' => KhidmatNasihat::STATUS_SELESAI,
            'created_at' => '2026-03-15 10:00:00',
        ], $attrs));
    }

    private function makeFeedback(KhidmatNasihat $kn, array $attrs = []): MaklumBalas
    {
        return MaklumBalas::create(array_merge([
            'khidmat_nasihat_id' => $kn->id,
            'soalan_2a' => 'BAIK',
        ], $attrs));
    }

    // ---- Landing / gating ----

    public function test_index_renders_for_role_with_laporan_view(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('laporan-kn.index'))
            ->assertOk()
            ->assertSee('Laporan Khidmat Nasihat');
    }

    public function test_index_forbidden_for_role_lacking_laporan_view(): void
    {
        // peguam lacks laporan.view and is NOT super-admin (admin bypasses via Gate::before).
        $this->actingAs($this->user('peguam@test.local'))->get(route('laporan-kn.index'))->assertStatus(302);
    }

    public function test_a_report_is_forbidden_for_role_lacking_laporan_view(): void
    {
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('laporan-kn.pandangan-uu'))
            ->assertStatus(302);
    }

    // ---- Report 1: Pandangan Undang-Undang (detail) ----

    public function test_pandangan_uu_lists_kn_with_legal_opinion(): void
    {
        $kn = $this->makeKn([
            'cawangan_id' => $this->branch('JBG PUTRAJAYA')->id,
            'id_kategori' => $this->kategoriA->id,
            'ulasan_pegawai' => self::TAG.' pandangan undang-undang di sini',
        ]);

        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.pandangan-uu'))
            ->assertOk()
            ->assertSee($kn->no_permohonan)
            ->assertSee(self::TAG.' pandangan undang-undang di sini');
    }

    public function test_pandangan_uu_filters_by_kategori_and_month_year(): void
    {
        $match = $this->makeKn([
            'id_kategori' => $this->kategoriA->id,
            'created_at' => '2026-03-10 09:00:00',
            'nama_mangsa' => self::TAG.' MatchUU',
        ]);
        $otherCat = $this->makeKn([
            'id_kategori' => $this->kategoriB->id,
            'created_at' => '2026-03-10 09:00:00',
            'nama_mangsa' => self::TAG.' OtherCatUU',
        ]);
        $otherMonth = $this->makeKn([
            'id_kategori' => $this->kategoriA->id,
            'created_at' => '2026-05-10 09:00:00',
            'nama_mangsa' => self::TAG.' OtherMonthUU',
        ]);

        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.pandangan-uu', ['id_kategori' => $this->kategoriA->id, 'bulan' => 3, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee($match->no_permohonan)
            ->assertDontSee($otherCat->no_permohonan)
            ->assertDontSee($otherMonth->no_permohonan);
    }

    public function test_pandangan_uu_excel_export_downloads_200(): void
    {
        $this->makeKn(['id_kategori' => $this->kategoriA->id, 'ulasan_pegawai' => self::TAG.' ulasan']);

        $res = $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.pandangan-uu.excel'));

        $res->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $res->headers->get('content-type')
        );
    }

    // ---- Report 6: Pendaftaran (detail) ----

    public function test_pendaftaran_lists_detail_with_appointment_date(): void
    {
        $putrajaya = $this->branch('JBG PUTRAJAYA');
        $kn = $this->makeKn(['cawangan_id' => $putrajaya->id, 'umur_mangsa' => '34']);
        $temu = TemuJanji::create([
            'id_khidmat_nasihat' => $kn->id,
            'cawangan_id' => $putrajaya->id,
            'tarikh_temu_janji' => '2026-04-20',
            'masa_mula' => '09:00:00', 'masa_akhir' => '09:30:00',
            'status' => 'DISAHKAN', 'cipta_oleh' => self::TAG,
        ]);
        $kn->update(['id_temu_janji' => $temu->id]);

        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.pendaftaran'))
            ->assertOk()
            ->assertSee($kn->no_permohonan)
            ->assertSee('2026-04-20');
    }

    public function test_pendaftaran_excel_export_downloads_200(): void
    {
        $this->makeKn();

        $res = $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.pendaftaran.excel'));

        $res->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $res->headers->get('content-type'));
    }

    // ---- Branch-scoping (KN has no CawanganScope) ----

    public function test_detail_report_is_branch_scoped_for_pinned_officer(): void
    {
        $putrajaya = $this->branch('JBG PUTRAJAYA');
        $selangor = $this->branch('JBG SELANGOR');

        $mine = $this->makeKn(['cawangan_id' => $putrajaya->id, 'nama_mangsa' => self::TAG.' Mine']);
        $other = $this->makeKn(['cawangan_id' => $selangor->id, 'nama_mangsa' => self::TAG.' Other']);

        // Pinned officer (no view-all) sees only own branch.
        $this->actingAs($this->officer('JBG PUTRAJAYA'))
            ->get(route('laporan-kn.pandangan-uu'))
            ->assertOk()
            ->assertSee($mine->no_permohonan)
            ->assertDontSee($other->no_permohonan);
    }

    public function test_view_all_user_sees_all_branches(): void
    {
        $putrajaya = $this->branch('JBG PUTRAJAYA');
        $selangor = $this->branch('JBG SELANGOR');

        $mine = $this->makeKn(['cawangan_id' => $putrajaya->id, 'nama_mangsa' => self::TAG.' Mine']);
        $other = $this->makeKn(['cawangan_id' => $selangor->id, 'nama_mangsa' => self::TAG.' Other']);

        // koordinator has cawangan.view-all -> sees both branches.
        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.pandangan-uu'))
            ->assertOk()
            ->assertSee($mine->no_permohonan)
            ->assertSee($other->no_permohonan);
    }

    // ---- Report 2: Cara Mengetahui JBG (maklum_balas bucket count) ----

    public function test_cara_mengetahui_counts_maklum_balas_buckets(): void
    {
        $kn1 = $this->makeKn();
        $this->makeFeedback($kn1, ['soalan_1a' => true, 'soalan_1b' => true]);
        $kn2 = $this->makeKn();
        $this->makeFeedback($kn2, ['soalan_1a' => true]);

        // 1a should be 2, 1b should be 1, 1c..1e 0.
        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.cara-mengetahui', ['bulan' => 3, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Portal')        // bucket label
            ->assertSee('Media Sosial'); // bucket label
    }

    public function test_cara_mengetahui_is_branch_scoped(): void
    {
        $putrajaya = $this->branch('JBG PUTRAJAYA');
        $selangor = $this->branch('JBG SELANGOR');

        $mine = $this->makeKn(['cawangan_id' => $putrajaya->id]);
        $this->makeFeedback($mine, ['soalan_1a' => true]);
        $other = $this->makeKn(['cawangan_id' => $selangor->id]);
        $this->makeFeedback($other, ['soalan_1a' => true, 'soalan_1b' => true]);

        $svc = app(\App\Support\LaporanKnService::class);

        // Pinned-officer branch filter (Putrajaya): only 1a=1, 1b=0.
        $counts = $svc->caraMengetahuiCounts(['bulan' => 3, 'tahun' => 2026, 'cawangan_id' => $putrajaya->id]);
        $this->assertSame(1, $counts['soalan_1a']);
        $this->assertSame(0, $counts['soalan_1b']);

        // All branches: 1a=2, 1b=1.
        $all = $svc->caraMengetahuiCounts(['bulan' => 3, 'tahun' => 2026]);
        $this->assertSame(2, $all['soalan_1a']);
        $this->assertSame(1, $all['soalan_1b']);
    }

    // ---- Report 7: Tahap Kepuasan Pelanggan (maklum_balas soalan_2a) ----

    public function test_kepuasan_counts_soalan_2a(): void
    {
        $k1 = $this->makeKn();
        $this->makeFeedback($k1, ['soalan_2a' => 'CEMERLANG']);
        $k2 = $this->makeKn();
        $this->makeFeedback($k2, ['soalan_2a' => 'CEMERLANG']);
        $k3 = $this->makeKn();
        $this->makeFeedback($k3, ['soalan_2a' => 'BAIK']);

        $svc = app(\App\Support\LaporanKnService::class);
        $counts = $svc->kepuasanCounts(['bulan' => 3, 'tahun' => 2026]);

        $this->assertSame(2, $counts['CEMERLANG']);
        $this->assertSame(1, $counts['BAIK']);
        $this->assertSame(0, $counts['KURANG_MEMUASKAN']);

        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.kepuasan', ['bulan' => 3, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('CEMERLANG');
    }

    // ---- Report 3: Mengikut Cawangan (branch × 12 months pivot) ----

    public function test_mengikut_cawangan_pivots_branch_by_month(): void
    {
        $putrajaya = $this->branch('JBG PUTRAJAYA');
        $this->makeKn(['cawangan_id' => $putrajaya->id, 'created_at' => '2026-03-01 10:00:00']);
        $this->makeKn(['cawangan_id' => $putrajaya->id, 'created_at' => '2026-03-20 10:00:00']);
        $this->makeKn(['cawangan_id' => $putrajaya->id, 'created_at' => '2026-07-01 10:00:00']);

        $svc = app(\App\Support\LaporanKnService::class);
        $pivot = $svc->pivotByCawangan(['tahun' => 2026]);

        // Find the Putrajaya row.
        $row = collect($pivot)->firstWhere('label', 'JBG PUTRAJAYA');
        $this->assertNotNull($row);
        $this->assertSame(2, $row['months'][3]); // March
        $this->assertSame(1, $row['months'][7]); // July
        $this->assertSame(0, $row['months'][1]); // January
    }

    public function test_mengikut_cawangan_requires_year_renders(): void
    {
        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.mengikut-cawangan', ['tahun' => 2026]))
            ->assertOk()
            ->assertSee('Mengikut Cawangan');
    }

    // ---- Report 4: Mengikut Kategori Kes (kategori × 12 months) ----

    public function test_mengikut_kategori_pivots_category_by_month(): void
    {
        $this->makeKn(['id_kategori' => $this->kategoriA->id, 'created_at' => '2026-03-01 10:00:00']);
        $this->makeKn(['id_kategori' => $this->kategoriA->id, 'created_at' => '2026-03-05 10:00:00']);
        $this->makeKn(['id_kategori' => $this->kategoriB->id, 'created_at' => '2026-06-05 10:00:00']);

        $svc = app(\App\Support\LaporanKnService::class);
        $pivot = $svc->pivotByKategori(['tahun' => 2026]);

        $rowA = collect($pivot)->firstWhere('label', self::TAG.' Kategori A');
        $this->assertNotNull($rowA);
        $this->assertSame(2, $rowA['months'][3]);
        $this->assertSame(0, $rowA['months'][6]);
    }

    // ---- Report 5: Mengikut Sub Kategori (subkategori × 12 months) ----

    public function test_mengikut_subkategori_pivots_sub_by_month(): void
    {
        $this->makeKn(['id_subkategori' => $this->subA->id, 'created_at' => '2026-02-01 10:00:00']);
        $this->makeKn(['id_subkategori' => $this->subA->id, 'created_at' => '2026-02-15 10:00:00']);

        $svc = app(\App\Support\LaporanKnService::class);
        $pivot = $svc->pivotBySubkategori(['tahun' => 2026]);

        $row = collect($pivot)->firstWhere('label', self::TAG.' Sub A');
        $this->assertNotNull($row);
        $this->assertSame(2, $row['months'][2]);
    }

    // ---- Report 8: Mengikut Kaum/Jantina (bangsa × jantina pivot) ----

    public function test_mengikut_kaum_jantina_pivots_bangsa_by_gender(): void
    {
        $this->makeKn(['bangsa' => self::TAG.'-MELAYU', 'jantina_mangsa' => 'Lelaki']);
        $this->makeKn(['bangsa' => self::TAG.'-MELAYU', 'jantina_mangsa' => 'Lelaki']);
        $this->makeKn(['bangsa' => self::TAG.'-MELAYU', 'jantina_mangsa' => 'Perempuan']);
        $this->makeKn(['bangsa' => self::TAG.'-CINA', 'jantina_mangsa' => 'Perempuan']);

        $svc = app(\App\Support\LaporanKnService::class);
        $pivot = $svc->pivotByKaumJantina(['bulan' => 3, 'tahun' => 2026]);

        $melayu = collect($pivot)->firstWhere('label', self::TAG.'-MELAYU');
        $this->assertNotNull($melayu);
        $this->assertSame(2, $melayu['Lelaki']);
        $this->assertSame(1, $melayu['Perempuan']);

        $cina = collect($pivot)->firstWhere('label', self::TAG.'-CINA');
        $this->assertSame(0, $cina['Lelaki']);
        $this->assertSame(1, $cina['Perempuan']);

        $this->actingAs($this->user('koordinator@test.local'))
            ->get(route('laporan-kn.kaum-jantina', ['bulan' => 3, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('Mengikut Kaum');
    }

    // ---- All 8 report routes resolve + gated ----

    public function test_all_eight_report_routes_render_for_view_all_user(): void
    {
        $coord = $this->user('koordinator@test.local');

        foreach ([
            'laporan-kn.pandangan-uu',
            'laporan-kn.cara-mengetahui',
            'laporan-kn.mengikut-cawangan',
            'laporan-kn.mengikut-kategori',
            'laporan-kn.mengikut-subkategori',
            'laporan-kn.pendaftaran',
            'laporan-kn.kepuasan',
            'laporan-kn.kaum-jantina',
        ] as $name) {
            $this->actingAs($coord)->get(route($name, ['tahun' => 2026]))->assertOk();
        }
    }
}
