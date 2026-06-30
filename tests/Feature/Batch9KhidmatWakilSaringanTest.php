<?php

namespace Tests\Feature;

use App\Models\Cawangan;
use App\Models\KhidmatNasihat;
use App\Models\MahkamahSivil;
use App\Models\MahkamahSyariah;
use App\Models\RefKategoriKn;
use App\Models\SlotTemuJanji;
use App\Models\TemuJanji;
use App\Models\User;
use App\Support\KhidmatBayaran;
use Carbon\Carbon;
use Database\Seeders\Batch8MastersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Batch 9 slice 3 — Sebagai-Wakil variants (penjara / JKM / mahkamah),
 * RM0 payment for penjara+JKM, and the 3-modal eligibility screening gate.
 *
 * Live mysql per repo convention; all rows + fixtures are PHPUNIT-tagged and
 * cleaned up in setUp/tearDown so the slice-3 data never leaks into real tables.
 */
class Batch9KhidmatWakilSaringanTest extends TestCase
{
    private const TAG = 'PHPUNIT';

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
        $this->seedFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        $ids = KhidmatNasihat::where('no_permohonan', 'like', self::TAG.'%')
            ->orWhere('nama_mangsa', 'like', self::TAG.'%')
            ->pluck('id');
        TemuJanji::whereIn('id_khidmat_nasihat', $ids)->delete();
        KhidmatNasihat::whereIn('id', $ids)->delete();
        // Tagged branches cascade their slots (FK); drop branches + court fixtures.
        Cawangan::where('nama', 'like', self::TAG.'%')->delete();
        MahkamahSivil::where('nama_mahkamah', 'like', self::TAG.'%')->delete();
        MahkamahSyariah::where('nama_mahkamah', 'like', self::TAG.'%')->delete();
    }

    /** A weekday well past the 4-working-day lead (mirrors Batch9CreateTest). */
    private function bookableDate(): Carbon
    {
        $d = Carbon::today()->addDays(10);
        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d;
    }

    /** Slice-3 needs PENJARA/JKM branches + court rows; Batch8 only seeds JBG. */
    private function seedFixtures(): void
    {
        $this->bookable = $this->bookableDate()->toDateString();
        $this->penjara = $this->branchWithSlot(self::TAG.' PENJARA KAJANG', 'PNJ', 'PENJARA');
        $this->jkm = $this->branchWithSlot(self::TAG.' JKM SELANGOR', 'JKM', 'JKM');
        $this->jbg = $this->branchWithSlot(self::TAG.' JBG TEST', 'TJB', 'JBG');
        $this->mahkamahSivil = MahkamahSivil::create(['nama_mahkamah' => self::TAG.' MAHKAMAH SIVIL SHAH ALAM', 'negeri_mahkamah' => 10, 'lokaliti_mahkamah' => 'SHAH ALAM', 'jenis_mahkamah' => 'TINGGI']);
        $this->mahkamahSyariah = MahkamahSyariah::create(['nama_mahkamah' => self::TAG.' MAHKAMAH SYARIAH SHAH ALAM', 'negeri_mahkamah' => 10, 'lokaliti_mahkamah' => 'SHAH ALAM', 'jenis_mahkamah' => 'RENDAH']);
        $this->kategoriSivil = RefKategoriKn::where('jenis_kategori', 'SIVIL')->firstOrFail();
        $this->kategoriPendamping = RefKategoriKn::where('jenis_kategori', 'PENDAMPING GUAMAN')->firstOrFail();
    }

    /** Tagged branch + one open future slot at 09:00 on the bookable date. */
    private function branchWithSlot(string $nama, string $kod, string $jenis): Cawangan
    {
        $branch = Cawangan::create(['nama' => $nama, 'kod' => $kod, 'jenis' => $jenis, 'negeri_id' => 16, 'status_aktif' => true]);
        SlotTemuJanji::create([
            'cawangan_id' => $branch->id,
            'tarikh_slot' => $this->bookable,
            'masa_mula' => '09:00',
            'masa_akhir' => '09:30',
            'is_temujanji' => false,
            'status_aktif' => true,
        ]);

        return $branch;
    }

    private Cawangan $penjara;

    private Cawangan $jkm;

    private Cawangan $jbg;

    private MahkamahSivil $mahkamahSivil;

    private MahkamahSyariah $mahkamahSyariah;

    private RefKategoriKn $kategoriSivil;

    private RefKategoriKn $kategoriPendamping;

    private string $bookable;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    /** Base wakil payload; merge overrides per context. */
    private function wakilPayload(array $overrides = []): array
    {
        return array_merge([
            'aksi' => 'hantar',
            'jenis_permohonan' => 'SEBAGAI_WAKIL',
            'jenis_wakil' => 'PENJARA',
            'nama_mangsa' => self::TAG.' Mangsa Wakil',
            'id_pengenalan_mangsa' => '900101105500',
            'nama_wakil' => 'Pegawai Wakil',
            'no_pengenalan_wakil' => '850202105511',
            'jawatan_wakil' => 'PEGAWAI PENJARA',
            'cawangan_id' => $this->penjara->id,
            'id_kategori' => $this->kategoriSivil->id,
            // screening already passed before reaching the wizard
            'saringan_jenis' => KhidmatNasihat::SARINGAN_SIVIL_SYARIAH,
            'saringan_lulus' => '1',
            'tarikh_temu_janji' => $this->bookable,
            'masa_temu_janji' => '09:00',
            'perakuan' => '1',
        ], $overrides);
    }

    // ---- KhidmatBayaran wakil contexts (pure unit) ----

    public function test_kira_wakil_penjara_and_jkm_are_free(): void
    {
        $this->assertSame(0.0, KhidmatBayaran::kira('SIVIL', 90000, false, 'PENJARA'));
        $this->assertSame(0.0, KhidmatBayaran::kira('SIVIL', 90000, false, 'JKM'));
        $this->assertSame(0.0, KhidmatBayaran::kira('SYARIAH', 5000, false, 'JKM'));
    }

    public function test_kira_wakil_mahkamah_keeps_normal_matrix(): void
    {
        // MAHKAMAH context is NOT auto-zeroed: normal income matrix applies.
        $this->assertSame(KhidmatBayaran::FI_ASAS, KhidmatBayaran::kira('SIVIL', 1000, false, 'MAHKAMAH'));
        $this->assertSame(KhidmatBayaran::FI_SUMBANGAN, KhidmatBayaran::kira('SIVIL', 90000, false, 'MAHKAMAH'));
    }

    public function test_kira_diri_sendiri_matrix_unchanged(): void
    {
        // Existing DIRI_SENDIRI behaviour (no wakil context) intact.
        $this->assertSame(KhidmatBayaran::FI_ASAS, KhidmatBayaran::kira('SIVIL', 1000));
        $this->assertSame(KhidmatBayaran::FI_SUMBANGAN, KhidmatBayaran::kira('SYARIAH', 90000));
        $this->assertSame(0.0, KhidmatBayaran::kira('PENDAMPING GUAMAN', 90000));
        $this->assertSame(0.0, KhidmatBayaran::kira('SIVIL', 90000, true));
    }

    // ---- Wakil store paths ----

    public function test_store_wakil_penjara_books_and_is_free(): void
    {
        $res = $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.store'), $this->wakilPayload([
                'jenis_wakil' => 'PENJARA',
                'cawangan_id' => $this->penjara->id,
                'jumlah_pendapatan' => 90000, // would be RM260 if not penjara
                'nama_diwakili' => self::TAG.' Banduan A',
                'id_pengenalan_diwakili' => '700303105522',
            ]));

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Wakil')->latest('id')->firstOrFail();
        $res->assertRedirect(route('khidmat.show', $row));
        $this->assertSame('SEBAGAI_WAKIL', $row->jenis_permohonan);
        $this->assertSame('PENJARA', $row->jenis_wakil);
        $this->assertSame('0.00', $row->jumlah_bayaran);
        $this->assertSame(KhidmatNasihat::STATUS_BAHARU, $row->status_kn);
        $this->assertSame(self::TAG.' Banduan A', $row->nama_diwakili);
    }

    public function test_store_wakil_jkm_is_free(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.store'), $this->wakilPayload([
                'jenis_wakil' => 'JKM',
                'cawangan_id' => $this->jkm->id,
                'jumlah_pendapatan' => 90000,
            ]))->assertStatus(302);

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Wakil')->latest('id')->firstOrFail();
        $this->assertSame('JKM', $row->jenis_wakil);
        $this->assertSame('0.00', $row->jumlah_bayaran);
    }

    public function test_store_wakil_mahkamah_sivil_persists_court_and_charges_matrix(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.store'), $this->wakilPayload([
                'jenis_wakil' => 'MAHKAMAH',
                'cawangan_id' => $this->jbg->id,
                'jenis_mahkamah_pihak' => 'SIVIL',
                'id_mahkamah' => $this->mahkamahSivil->id,
                'id_kategori' => $this->kategoriSivil->id,
                'jumlah_pendapatan' => 90000, // > 50k -> Sumbangan RM260
            ]))->assertStatus(302);

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Wakil')->latest('id')->firstOrFail();
        $this->assertSame('MAHKAMAH', $row->jenis_wakil);
        $this->assertSame('SIVIL', $row->jenis_mahkamah_pihak);
        $this->assertSame($this->mahkamahSivil->id, (int) $row->id_mahkamah);
        $this->assertSame('260.00', $row->jumlah_bayaran);
        // mahkamah() accessor resolves to the sivil court.
        $this->assertSame($this->mahkamahSivil->id, $row->mahkamah()->id);
    }

    public function test_store_wakil_mahkamah_syariah_resolves_syariah_court(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.store'), $this->wakilPayload([
                'jenis_wakil' => 'MAHKAMAH',
                'cawangan_id' => $this->jbg->id,
                'jenis_mahkamah_pihak' => 'SYARIAH',
                'id_mahkamah' => $this->mahkamahSyariah->id,
                'jumlah_pendapatan' => 1000,
            ]))->assertStatus(302);

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Wakil')->latest('id')->firstOrFail();
        $this->assertSame('SYARIAH', $row->jenis_mahkamah_pihak);
        $this->assertSame('10.00', $row->jumlah_bayaran);
        $this->assertInstanceOf(MahkamahSyariah::class, $row->mahkamah());
        $this->assertSame($this->mahkamahSyariah->id, $row->mahkamah()->id);
    }

    public function test_store_wakil_requires_jenis_wakil(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.store'), $this->wakilPayload(['jenis_wakil' => null]))
            ->assertSessionHasErrors('jenis_wakil');
    }

    public function test_store_wakil_mahkamah_requires_court_selection(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.store'), $this->wakilPayload([
                'jenis_wakil' => 'MAHKAMAH',
                'cawangan_id' => $this->jbg->id,
                'jenis_mahkamah_pihak' => null,
                'id_mahkamah' => null,
            ]))
            ->assertSessionHasErrors(['jenis_mahkamah_pihak', 'id_mahkamah']);
    }

    // ---- Wizard render (wakil + mahkamah Blade) ----

    public function test_create_form_renders_wakil_and_mahkamah_controls(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('khidmat.create'))
            ->assertOk()
            ->assertSee('Sebagai Wakil')
            ->assertSee('Konteks Wakil')
            ->assertSee('Maklumat Mahkamah')
            ->assertSee($this->mahkamahSivil->nama_mahkamah)
            ->assertSee($this->mahkamahSyariah->nama_mahkamah);
    }

    public function test_show_renders_wakil_card_for_mahkamah_application(): void
    {
        $row = KhidmatNasihat::create([
            'no_permohonan' => self::TAG.'-WK-'.uniqid(),
            'jenis_permohonan' => 'SEBAGAI_WAKIL',
            'jenis_wakil' => 'MAHKAMAH',
            'nama_mangsa' => self::TAG.' Mangsa Show',
            'nama_wakil' => 'Peguam Wakil',
            'jenis_mahkamah_pihak' => 'SIVIL',
            'id_mahkamah' => $this->mahkamahSivil->id,
        ]);

        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('khidmat.show', $row))
            ->assertOk()
            ->assertSee('Wakil &amp; Diwakili', false)
            ->assertSee($this->mahkamahSivil->nama_mahkamah);
    }

    // ---- Eligibility 3-modal screening gate ----

    public function test_saringan_page_renders(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->get(route('khidmat.saringan'))
            ->assertOk()
            ->assertSee('Saringan')
            ->assertSee('Terma');
    }

    public function test_saringan_blocks_when_eligibility_questions_fail(): void
    {
        // Q "pernah terima nasihat" = answered disqualifying -> blocked, no proceed.
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.saringan.semak'), [
                'saringan_jenis' => 'sivil_syariah',
                'pendapatan_bawah_had' => 'Ya',
                'tiada_nasihat_terdahulu' => 'Tidak', // disqualifies
                'tiada_perkara_dikecualikan' => 'Ya',
                'terma' => '1',
            ])
            ->assertRedirect(route('khidmat.saringan'))
            ->assertSessionHas('saringan_gagal');
    }

    public function test_saringan_blocks_when_terms_not_accepted(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.saringan.semak'), [
                'saringan_jenis' => 'sivil_syariah',
                'pendapatan_bawah_had' => 'Ya',
                'tiada_nasihat_terdahulu' => 'Ya',
                'tiada_perkara_dikecualikan' => 'Ya',
                'terma' => null, // not accepted
            ])
            ->assertSessionHasErrors('terma');
    }

    public function test_saringan_passes_and_redirects_to_create_carrying_outcome(): void
    {
        $res = $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.saringan.semak'), [
                'saringan_jenis' => 'sivil_syariah',
                'pendapatan_bawah_had' => 'Ya', // <= 50k, not sumbangan
                'tiada_nasihat_terdahulu' => 'Ya',
                'tiada_perkara_dikecualikan' => 'Ya',
                'terma' => '1',
            ]);

        $res->assertRedirect(route('khidmat.create'));
        $this->assertSame(KhidmatNasihat::SARINGAN_SIVIL_SYARIAH, session('saringan.jenis'));
        $this->assertTrue(session('saringan.lulus'));
        $this->assertFalse(session('saringan.sumbangan'));
    }

    public function test_saringan_sivil_syariah_above_had_marks_sumbangan(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.saringan.semak'), [
                'saringan_jenis' => 'sivil_syariah',
                'pendapatan_bawah_had' => 'Tidak', // > 50k -> Laluan Sumbangan
                'tiada_nasihat_terdahulu' => 'Ya',
                'tiada_perkara_dikecualikan' => 'Ya',
                'terma' => '1',
            ])
            ->assertRedirect(route('khidmat.create'));

        $this->assertTrue(session('saringan.sumbangan'));
    }

    public function test_saringan_pendamping_skips_income_gate(): void
    {
        // Pendamping path has no income limit; passes without pendapatan_bawah_had.
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.saringan.semak'), [
                'saringan_jenis' => 'pendamping_jenayah',
                'tiada_nasihat_terdahulu' => 'Ya',
                'tiada_perkara_dikecualikan' => 'Ya',
                'terma' => '1',
            ])
            ->assertRedirect(route('khidmat.create'));

        $this->assertSame(KhidmatNasihat::SARINGAN_PENDAMPING, session('saringan.jenis'));
        $this->assertTrue(session('saringan.lulus'));
        $this->assertFalse(session('saringan.sumbangan'));
    }

    public function test_saringan_gate_permission_blocks_peguam(): void
    {
        $this->actingAs($this->user('peguam@test.local'))
            ->get(route('khidmat.saringan'))
            ->assertStatus(302);
    }

    // ---- Gate enforcement at store() ----

    public function test_store_diri_sendiri_hantar_blocked_without_session_pass(): void
    {
        // A citizen DIRI_SENDIRI final submit that never cleared screening is
        // rejected (403) — the pass flag is read from the session, not the client.
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.store'), [
                'aksi' => 'hantar',
                'jenis_permohonan' => 'DIRI_SENDIRI',
                'nama_mangsa' => self::TAG.' Mangsa NoGate',
                'id_pengenalan_mangsa' => '900101105500',
                'cawangan_id' => $this->jbg->id,
                'id_kategori' => $this->kategoriSivil->id,
                'tarikh_temu_janji' => $this->bookable,
                'masa_temu_janji' => '09:00',
                'perakuan' => '1',
                'saringan_lulus' => '1', // tampered hidden field — must be ignored
            ])
            ->assertStatus(403);

        $this->assertNull(KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa NoGate')->first());
    }

    public function test_store_diri_sendiri_draft_allowed_without_gate(): void
    {
        // Draft saves are never gated (incomplete by design).
        $this->actingAs($this->user('pegawai@test.local'))
            ->post(route('khidmat.store'), [
                'aksi' => 'draf',
                'jenis_permohonan' => 'DIRI_SENDIRI',
                'nama_mangsa' => self::TAG.' Mangsa Draf',
                'cawangan_id' => $this->jbg->id,
            ])
            ->assertRedirect();

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Draf')->firstOrFail();
        $this->assertSame(KhidmatNasihat::STATUS_DRAF, $row->status_kn);
    }

    public function test_store_diri_sendiri_hantar_permitted_with_session_pass(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))
            ->withSession(['saringan' => ['jenis' => KhidmatNasihat::SARINGAN_SIVIL_SYARIAH, 'lulus' => true, 'sumbangan' => false]])
            ->post(route('khidmat.store'), [
                'aksi' => 'hantar',
                'jenis_permohonan' => 'DIRI_SENDIRI',
                'nama_mangsa' => self::TAG.' Mangsa Lulus',
                'id_pengenalan_mangsa' => '900101105500',
                'cawangan_id' => $this->jbg->id,
                'id_kategori' => $this->kategoriSivil->id,
                'tarikh_temu_janji' => $this->bookable,
                'masa_temu_janji' => '09:00',
                'perakuan' => '1',
            ])
            ->assertStatus(302);

        $row = KhidmatNasihat::where('nama_mangsa', self::TAG.' Mangsa Lulus')->firstOrFail();
        $this->assertSame(KhidmatNasihat::STATUS_BAHARU, $row->status_kn);
        // Screening outcome persisted from the SESSION, not the client field.
        $this->assertTrue($row->saringan_lulus);
        $this->assertSame(KhidmatNasihat::SARINGAN_SIVIL_SYARIAH, $row->saringan_jenis);
    }
}
