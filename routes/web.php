<?php

use App\Http\Controllers\AgihanController;
use App\Http\Controllers\AgihanSpineController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Awam\PermohonanController;
use App\Http\Controllers\Awam\PortalController;
use App\Http\Controllers\Awam\PublicAuthController;
use App\Http\Controllers\CawanganController;
use App\Http\Controllers\CetakanController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\CutiController;
use App\Http\Controllers\CutiNegeriController;
use App\Http\Controllers\JadualJanjiTemuController;
use App\Http\Controllers\JawatanController;
use App\Http\Controllers\KategoriKnController;
use App\Http\Controllers\KemaskiniBidangController;
use App\Http\Controllers\KeputusanController;
use App\Http\Controllers\KesController;
use App\Http\Controllers\KesilapanController;
use App\Http\Controllers\KhidmatNasihatController;
use App\Http\Controllers\KhidmatProsesController;
use App\Http\Controllers\LejarTuntutanController;
use App\Http\Controllers\Peguam\TuntutanController as PeguamTuntutanController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LampiranController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LaporanKhidmatNasihatController;
use App\Http\Controllers\LaporanPenuhController;
use App\Http\Controllers\MahkamahController;
use App\Http\Controllers\MahkamahRefController;
use App\Http\Controllers\MaklumBalasController;
use App\Http\Controllers\OydController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PeguamController;
use App\Http\Controllers\PeguamDaftarController;
use App\Http\Controllers\PeguamPanelController;
use App\Http\Controllers\PembelaanAwamController;
use App\Http\Controllers\PengantaraanController;
use App\Http\Controllers\PenutupanOperasiController;
use App\Http\Controllers\PermohonanPeguamController;
use App\Http\Controllers\PosterController;
use App\Http\Controllers\RefKesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SlotController;
use App\Http\Controllers\SlotGenerationController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\StatistikPengantaraanController;
use App\Http\Controllers\StatistikSlaController;
use App\Http\Controllers\SystemAuthController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TarikDiriController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Public landing.
Route::get('/', fn () => view('welcome'))->name('home');

// Public AI@JBG chatbot proxy (widget on the landing page → Python microservice). Throttled.
Route::post('/chatbot/ask', [ChatbotController::class, 'ask'])
    ->middleware('throttle:20,1')
    ->name('chatbot.ask');

// Public lawyer panel application (no login — prospective panel lawyers). Throttled + honeypot.
Route::get('/peguam/daftar', [PeguamDaftarController::class, 'create'])->name('peguam.daftar');
Route::post('/peguam/daftar', [PeguamDaftarController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('peguam.daftar.store');

// Public application-status lookup by IC (legacy semak.php parity). Throttled + honeypot.
Route::get('/peguam/semak-status', [PeguamDaftarController::class, 'semakStatus'])->name('peguam.semak-status');
Route::post('/peguam/semak-status', [PeguamDaftarController::class, 'semakStatusCheck'])
    ->middleware('throttle:10,1')
    ->name('peguam.semak-status.check');

// ---- Public Awam portal: guest auth (IC login). Captcha + throttle + honeypot. ----
Route::middleware('guest')->group(function () {
    Route::get('/awam/daftar', [PublicAuthController::class, 'showDaftar'])->name('awam.daftar');
    Route::post('/awam/daftar', [PublicAuthController::class, 'daftar'])
        ->middleware('throttle:6,1')->name('awam.daftar.store');

    Route::get('/awam/login', [PublicAuthController::class, 'showLogin'])->name('awam.login');
    Route::post('/awam/login', [PublicAuthController::class, 'login'])
        ->middleware('throttle:10,1')->name('awam.login.attempt');
});

Route::post('/awam/logout', [PublicAuthController::class, 'logout'])
    ->middleware('auth')->name('awam.logout');

// Citizen portal (Batch 13): dashboard + KN application wizard.
Route::middleware(['auth', 'permission:awam.portal'])->prefix('awam')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('awam.dashboard');

    Route::get('/permohonan/saringan', [PermohonanController::class, 'saringan'])->name('awam.permohonan.saringan');
    Route::post('/permohonan/saringan', [PermohonanController::class, 'saringanSemak'])->name('awam.permohonan.saringan.semak');
    Route::get('/permohonan/baharu', [PermohonanController::class, 'create'])->name('awam.permohonan.create');
    Route::post('/permohonan', [PermohonanController::class, 'store'])->middleware('throttle:10,1')->name('awam.permohonan.store');
    Route::get('/permohonan/{khidmat}', [PermohonanController::class, 'show'])->name('awam.permohonan.show')->whereNumber('khidmat');
    Route::post('/permohonan/{khidmat}/batal', [PermohonanController::class, 'cancel'])->name('awam.permohonan.batal')->whereNumber('khidmat');
    Route::post('/permohonan/{khidmat}/jadual-semula', [PermohonanController::class, 'reschedule'])->name('awam.permohonan.reschedule')->whereNumber('khidmat');
    Route::post('/permohonan/{khidmat}/lampiran', [PermohonanController::class, 'upload'])->middleware('throttle:20,1')->name('awam.lampiran.store')->whereNumber('khidmat');
    Route::get('/permohonan/{khidmat}/lampiran/{fail}/muat-turun', [PermohonanController::class, 'download'])->name('awam.lampiran.download')->whereNumber('khidmat')->whereNumber('fail');
});

// Slot availability JSON — shared: staff (slot.view) AND citizens (awam.portal) need these.
Route::middleware(['auth', 'permission:slot.view|awam.portal'])->group(function () {
    Route::get('/slot/tarikh', [SlotController::class, 'availability'])->name('slot.tarikh');
    Route::get('/slot/masa', [SlotController::class, 'times'])->name('slot.masa');
});

// ---- Guest: login + password reset (plain Laravel, no Filament) ----
Route::middleware('guest')->group(function () {
    Route::get('/system/login', [SystemAuthController::class, 'showLogin'])->name('system.login');
    Route::post('/system/login', [SystemAuthController::class, 'attempt'])->middleware('throttle:10,1')->name('system.login.attempt');

    Route::get('/password/forgot', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/password/forgot', [PasswordResetController::class, 'email'])->name('password.email');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'update'])->name('password.update');
});

// ---- Authenticated (any role) ----
Route::middleware('auth')->group(function () {
    Route::post('/logout', [SystemAuthController::class, 'logout'])->name('system.logout');

    // Forced / voluntary password change (migrated accounts are pinned here until done).
    Route::get('/password/change', [SystemAuthController::class, 'showChangePassword'])->name('password.change');
    Route::post('/password/change', [SystemAuthController::class, 'changePassword'])->name('password.change.update');
});

// ---- Staff area: rekod-kes + panel admin (admin / pengarah / koordinator / pegawai) ----
Route::middleware(['auth', 'permission:system.view'])->group(function () {
    Route::get('/system', [SystemController::class, 'utama'])->name('system.utama');

    // Rekod kes (Case backbone + permohonan CRUD)
    Route::get('/kes', [KesController::class, 'index'])->name('kes.index');
    Route::get('/fail-tutup', [KesController::class, 'tutup'])->name('kes.tutup');
    Route::get('/kes/create', [KesController::class, 'create'])->name('kes.create');
    Route::get('/kes/semak-nokp', [KesController::class, 'checkNokp'])->name('kes.semak-nokp');
    Route::post('/kes', [KesController::class, 'store'])->name('kes.store');
    Route::get('/kes/{kes}/edit', [KesController::class, 'edit'])->name('kes.edit')->whereNumber('kes');
    Route::put('/kes/{kes}', [KesController::class, 'update'])->name('kes.update')->whereNumber('kes');
    Route::get('/kes/{kes}', [KesController::class, 'show'])->name('kes.show')->whereNumber('kes');

    // Keputusan Pengarah (peringkat 2 approve/reject) + Tutup Fail (peringkat 7) — gated in controller
    Route::post('/kes/{kes}/lulus', [KeputusanController::class, 'lulus'])->name('kes.lulus')->whereNumber('kes');
    Route::post('/kes/{kes}/tolak', [KeputusanController::class, 'tolak'])->name('kes.tolak')->whereNumber('kes');
    Route::post('/kes/{kes}/tutup-fail', [KeputusanController::class, 'tutupFail'])->name('kes.tutupfail')->whereNumber('kes');

    // W16 — Pengesahan Selesai: cases a panel lawyer marked selesai (18) → JBG confirm (19) / return (2). Gated in controller.
    Route::get('/kes-selesai', [KeputusanController::class, 'senaraiSelesai'])->name('keputusan.selesai');
    Route::post('/kes/{kes}/sahkan-selesai', [KeputusanController::class, 'sahkanSelesai'])->name('keputusan.kes.sahkan-selesai')->whereNumber('kes');
    Route::post('/kes/{kes}/tolak-selesai', [KeputusanController::class, 'tolakSelesai'])->name('keputusan.kes.tolak-selesai')->whereNumber('kes');

    // Pengantaraan (mediation) — section edit + hearing reschedule
    Route::get('/kes/{kes}/pengantaraan', [PengantaraanController::class, 'edit'])->name('pengantaraan.edit')->whereNumber('kes');
    Route::put('/kes/{kes}/pengantaraan', [PengantaraanController::class, 'update'])->name('pengantaraan.update')->whereNumber('kes');
    Route::post('/kes/{kes}/sidang', [PengantaraanController::class, 'tangguhSidang'])->name('sidang.tangguh')->whereNumber('kes');

    // Kes Mahkamah (court) — section edit + laporan_kes child records
    Route::get('/kes/{kes}/mahkamah', [MahkamahController::class, 'edit'])->name('mahkamah.edit')->whereNumber('kes');
    Route::put('/kes/{kes}/mahkamah', [MahkamahController::class, 'update'])->name('mahkamah.update')->whereNumber('kes');
    Route::post('/kes/{kes}/laporan', [MahkamahController::class, 'storeLaporan'])->name('laporan.store')->whereNumber('kes');
    Route::delete('/kes/{kes}/laporan/{laporan}', [MahkamahController::class, 'destroyLaporan'])->name('laporan.destroy')->whereNumber('kes')->whereNumber('laporan');

    // Lampiran (case attachments) — private disk, auth-streamed
    Route::post('/kes/{kes}/lampiran', [LampiranController::class, 'store'])->name('lampiran.store')->whereNumber('kes');
    Route::get('/lampiran/{lampiran}/muat-turun', [LampiranController::class, 'download'])->name('lampiran.download')->whereNumber('lampiran');
    Route::delete('/kes/{kes}/lampiran/{lampiran}', [LampiranController::class, 'destroy'])->name('lampiran.destroy')->whereNumber('kes')->whereNumber('lampiran');

    // Cetakan (per-case printouts → dompdf, inline stream)
    Route::get('/kes/{kes}/cetak/ringkasan', [CetakanController::class, 'ringkasan'])->name('cetak.ringkasan')->whereNumber('kes');
    Route::get('/kes/{kes}/cetak/penugasan', [CetakanController::class, 'agihan'])->name('cetak.penugasan')->whereNumber('kes');
    Route::get('/kes/{kes}/cetak/laporan', [CetakanController::class, 'laporan'])->name('cetak.laporan')->whereNumber('kes');
    Route::get('/kes/{kes}/cetak/penutupan', [CetakanController::class, 'penutupan'])->name('cetak.penutupan')->whereNumber('kes');
    Route::get('/kes/{kes}/cetak/perakuan', [CetakanController::class, 'perakuan'])->name('cetak.perakuan')->whereNumber('kes'); // W14 legal-aid certificate
    Route::get('/kes/{kes}/cetak/pembatalan', [CetakanController::class, 'pembatalan'])->name('cetak.pembatalan')->whereNumber('kes'); // W20 cancellation letter

    // OYD (Orang Yang Dibantu) registry
    Route::get('/oyd', [OydController::class, 'index'])->name('oyd.index');
    Route::get('/oyd/create', [OydController::class, 'create'])->name('oyd.create');
    Route::post('/oyd', [OydController::class, 'store'])->name('oyd.store');
    Route::get('/oyd/{oyd}/edit', [OydController::class, 'edit'])->name('oyd.edit')->whereNumber('oyd');
    Route::put('/oyd/{oyd}', [OydController::class, 'update'])->name('oyd.update')->whereNumber('oyd');
    Route::get('/oyd/{oyd}', [OydController::class, 'show'])->name('oyd.show')->whereNumber('oyd');

    // KPI dashboard (yearly SLA prestasi)
    Route::get('/kpi', [KpiController::class, 'index'])->name('kpi.index');

    // Laporan (litigasi + pengantaraan) — table + CSV/PDF export
    $laporanTypes = ['permohonan', 'pendaftaran-fail', 'status-fail', 'penugasan-pengantaraan', 'pencapaian-pengantaraan', 'tidak-dirujuk'];
    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/{type}/csv', [LaporanController::class, 'csv'])->name('laporan.csv')->whereIn('type', $laporanTypes);
    Route::get('/laporan/{type}/pdf', [LaporanController::class, 'pdf'])->name('laporan.pdf')->whereIn('type', $laporanTypes);
    // W20 — queued bulk .xlsx export + download of the finished file.
    Route::get('/laporan/{type}/eksport-pukal', [LaporanController::class, 'eksportPukal'])->name('laporan.eksport-pukal')->whereIn('type', $laporanTypes);
    Route::get('/laporan/muat-turun/{fail}', [LaporanController::class, 'muatTurunEksport'])->name('laporan.muat-turun')->where('fail', '[A-Za-z0-9\-\.]+');
    // Wide-column CSV exports (EPIC F — legacy export_*.php): full legacy column parity.
    Route::get('/laporan/{type}/eksport-penuh', [LaporanPenuhController::class, 'csv'])
        ->middleware('permission:laporan.view')
        ->whereIn('type', ['permohonan', 'pendaftaran-fail', 'status-fail', 'penugasan-pengantaraan', 'tidak-dirujuk'])
        ->name('laporan.penuh');
    Route::get('/laporan/{type}', [LaporanController::class, 'show'])->name('laporan.show')->whereIn('type', $laporanTypes);

    // Statistik + exports
    Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik.index');
    Route::get('/statistik/excel', [StatistikController::class, 'excel'])->name('statistik.excel');
    Route::get('/statistik/pdf', [StatistikController::class, 'pdf'])->name('statistik.pdf');

    // Statistik SLA — per-branch achievement matrices (EPIC F, all-branch aggregate).
    Route::middleware('permission:statistik.view')->group(function () {
        Route::get('/statistik-sla', [StatistikSlaController::class, 'index'])->name('statistik-sla.index');
        Route::get('/statistik-sla/{key}', [StatistikSlaController::class, 'show'])->name('statistik-sla.show');
        Route::get('/statistik-sla/{key}/pdf', [StatistikSlaController::class, 'pdf'])->name('statistik-sla.pdf');
        // Breach "senarai" CSV — the TIDAK CAPAI drill-down behind each matrix (P1, legacy export_senarai_*).
        Route::get('/statistik-sla/{key}/senarai', [StatistikSlaController::class, 'senarai'])->name('statistik-sla.senarai');

        // Kesilapan Penjanaan Nombor Fail — per-month count matrix + wide CSV (P1).
        Route::get('/statistik-kesilapan', [KesilapanController::class, 'index'])->name('statistik-kesilapan.index');
        Route::get('/statistik-kesilapan/csv', [KesilapanController::class, 'csv'])->name('statistik-kesilapan.csv');

        // Statistik Penugasan Pengantaraan — branch×kategori + branch×month assignment matrices (P1).
        Route::get('/statistik-pengantaraan', [StatistikPengantaraanController::class, 'index'])->name('statistik-pengantaraan.index');
        Route::get('/statistik-pengantaraan/kategori', [StatistikPengantaraanController::class, 'kategori'])->name('statistik-pengantaraan.kategori');
        Route::get('/statistik-pengantaraan/bulanan', [StatistikPengantaraanController::class, 'bulanan'])->name('statistik-pengantaraan.bulanan');
        Route::get('/statistik-pengantaraan/pencapaian', [StatistikPengantaraanController::class, 'pencapaian'])->name('statistik-pengantaraan.pencapaian');
        Route::get('/statistik-pengantaraan/{jenis}/pdf', [StatistikPengantaraanController::class, 'pdf'])
            ->whereIn('jenis', ['kategori', 'bulanan', 'pencapaian'])->name('statistik-pengantaraan.pdf');
    });

    // Selenggara (maintenance) + Pegawai JBG registry + Audit log — gated per-resource permission
    Route::middleware('permission:selenggara.pegawai')->group(function () {
        Route::get('/pegawai', [PegawaiController::class, 'index'])->name('pegawai.index');
        Route::get('/pegawai/create', [PegawaiController::class, 'create'])->name('pegawai.create');
        Route::post('/pegawai', [PegawaiController::class, 'store'])->name('pegawai.store');
        Route::get('/pegawai/{pegawai}/edit', [PegawaiController::class, 'edit'])->name('pegawai.edit')->whereNumber('pegawai');
        Route::put('/pegawai/{pegawai}', [PegawaiController::class, 'update'])->name('pegawai.update')->whereNumber('pegawai');
        Route::delete('/pegawai/{pegawai}', [PegawaiController::class, 'destroy'])->name('pegawai.destroy')->whereNumber('pegawai');
    });

    // e-Poster
    Route::middleware('permission:selenggara.poster')->group(function () {
        Route::get('/poster', [PosterController::class, 'index'])->name('poster.index');
        Route::get('/poster/create', [PosterController::class, 'create'])->name('poster.create');
        Route::post('/poster', [PosterController::class, 'store'])->name('poster.store');
        Route::get('/poster/{poster}/edit', [PosterController::class, 'edit'])->name('poster.edit')->whereNumber('poster');
        Route::put('/poster/{poster}', [PosterController::class, 'update'])->name('poster.update')->whereNumber('poster');
        Route::delete('/poster/{poster}', [PosterController::class, 'destroy'])->name('poster.destroy')->whereNumber('poster');
    });

    // Jenis Kes (ref_kes)
    Route::middleware('permission:selenggara.ref_kes')->group(function () {
        Route::get('/ref-kes', [RefKesController::class, 'index'])->name('ref-kes.index');
        Route::get('/ref-kes/create', [RefKesController::class, 'create'])->name('ref-kes.create');
        Route::post('/ref-kes', [RefKesController::class, 'store'])->name('ref-kes.store');
        Route::get('/ref-kes/{ref_kes}/edit', [RefKesController::class, 'edit'])->name('ref-kes.edit')->whereNumber('ref_kes');
        Route::put('/ref-kes/{ref_kes}', [RefKesController::class, 'update'])->name('ref-kes.update')->whereNumber('ref_kes');
        Route::delete('/ref-kes/{ref_kes}', [RefKesController::class, 'destroy'])->name('ref-kes.destroy')->whereNumber('ref_kes');
    });

    // Mahkamah reference (sivil + syariah)
    Route::middleware('permission:selenggara.mahkamah_ref')->group(function () {
        Route::get('/mahkamah-ref/{jenis}', [MahkamahRefController::class, 'index'])->name('mahkamah-ref.index')->whereIn('jenis', ['sivil', 'syariah']);
        Route::get('/mahkamah-ref/{jenis}/create', [MahkamahRefController::class, 'create'])->name('mahkamah-ref.create')->whereIn('jenis', ['sivil', 'syariah']);
        Route::post('/mahkamah-ref/{jenis}', [MahkamahRefController::class, 'store'])->name('mahkamah-ref.store')->whereIn('jenis', ['sivil', 'syariah']);
        Route::get('/mahkamah-ref/{jenis}/{id}/edit', [MahkamahRefController::class, 'edit'])->name('mahkamah-ref.edit')->whereIn('jenis', ['sivil', 'syariah'])->whereNumber('id');
        Route::put('/mahkamah-ref/{jenis}/{id}', [MahkamahRefController::class, 'update'])->name('mahkamah-ref.update')->whereIn('jenis', ['sivil', 'syariah'])->whereNumber('id');
        Route::delete('/mahkamah-ref/{jenis}/{id}', [MahkamahRefController::class, 'destroy'])->name('mahkamah-ref.destroy')->whereIn('jenis', ['sivil', 'syariah'])->whereNumber('id');
    });

    // Cuti Umum (ref_cuti) — public-holiday reference master
    Route::middleware('permission:selenggara.cuti')->group(function () {
        Route::get('/cuti', [CutiController::class, 'index'])->name('cuti.index');
        Route::get('/cuti/create', [CutiController::class, 'create'])->name('cuti.create');
        Route::post('/cuti', [CutiController::class, 'store'])->name('cuti.store');
        Route::get('/cuti/{cuti}/edit', [CutiController::class, 'edit'])->name('cuti.edit')->whereNumber('cuti');
        Route::put('/cuti/{cuti}', [CutiController::class, 'update'])->name('cuti.update')->whereNumber('cuti');
        Route::delete('/cuti/{cuti}', [CutiController::class, 'destroy'])->name('cuti.destroy')->whereNumber('cuti');
    });

    // Cawangan master (JBG/JKM/Penjara) + bilik (rooms) — Janji Temu foundation
    Route::middleware('permission:selenggara.cawangan')->group(function () {
        Route::get('/cawangan', [CawanganController::class, 'index'])->name('cawangan.index');
        Route::get('/cawangan/create', [CawanganController::class, 'create'])->name('cawangan.create');
        Route::post('/cawangan', [CawanganController::class, 'store'])->name('cawangan.store');
        Route::get('/cawangan/{cawangan}/edit', [CawanganController::class, 'edit'])->name('cawangan.edit')->whereNumber('cawangan');
        Route::put('/cawangan/{cawangan}', [CawanganController::class, 'update'])->name('cawangan.update')->whereNumber('cawangan');
        Route::delete('/cawangan/{cawangan}', [CawanganController::class, 'destroy'])->name('cawangan.destroy')->whereNumber('cawangan');
        Route::post('/cawangan/{cawangan}/bilik', [CawanganController::class, 'storeBilik'])->name('cawangan.bilik.store')->whereNumber('cawangan');
        Route::delete('/cawangan/{cawangan}/bilik/{bilik}', [CawanganController::class, 'destroyBilik'])->name('cawangan.bilik.destroy')->whereNumber('cawangan')->whereNumber('bilik');
    });

    // Janji Temu slot availability (read-only JSON) — slot.tarikh/slot.masa moved to
    // a shared group below (staff + citizen both need these; see permission:slot.view|awam.portal).

    // Kalendar / Slot admin (batch 10 slice 2): slot auto-generation + per-branch session
    // config ("penetapan sesi") + operational closures. Gated permission:slot.manage.
    Route::middleware('permission:slot.manage')->group(function () {
        Route::get('/slot', [SlotGenerationController::class, 'index'])->name('slot.index');
        Route::put('/slot/cawangan/{cawangan}/sesi', [SlotGenerationController::class, 'updateSession'])->name('slot.sesi')->whereNumber('cawangan');
        Route::post('/slot/jana', [SlotGenerationController::class, 'generate'])->name('slot.generate');
        Route::delete('/slot/jana', [SlotGenerationController::class, 'destroy'])->name('slot.destroy');

        Route::get('/penutupan-operasi', [PenutupanOperasiController::class, 'index'])->name('penutupan.index');
        Route::get('/penutupan-operasi/create', [PenutupanOperasiController::class, 'create'])->name('penutupan.create');
        Route::post('/penutupan-operasi', [PenutupanOperasiController::class, 'store'])->name('penutupan.store');
        Route::delete('/penutupan-operasi/{penutupan}', [PenutupanOperasiController::class, 'destroy'])->name('penutupan.destroy')->whereNumber('penutupan');
    });

    // Jenis Khidmat (KN category tree: kategori -> kes -> subkategori)
    Route::middleware('permission:selenggara.kategori_kn')->group(function () {
        Route::get('/kategori-kn', [KategoriKnController::class, 'index'])->name('kategori-kn.index');
        Route::post('/kategori-kn', [KategoriKnController::class, 'store'])->name('kategori-kn.store');
        Route::put('/kategori-kn/{kategori}', [KategoriKnController::class, 'update'])->name('kategori-kn.update')->whereNumber('kategori');
        Route::delete('/kategori-kn/{kategori}', [KategoriKnController::class, 'destroy'])->name('kategori-kn.destroy')->whereNumber('kategori');
        Route::get('/kategori-kn/{kategori}/kes', [KategoriKnController::class, 'kes'])->name('kategori-kn.kes')->whereNumber('kategori');
        Route::post('/kategori-kn/{kategori}/kes', [KategoriKnController::class, 'storeKes'])->name('kategori-kn.kes.store')->whereNumber('kategori');
        Route::put('/kategori-kn/kes/{kes}', [KategoriKnController::class, 'updateKes'])->name('kategori-kn.kes.update')->whereNumber('kes');
        Route::delete('/kategori-kn/kes/{kes}', [KategoriKnController::class, 'destroyKes'])->name('kategori-kn.kes.destroy')->whereNumber('kes');
        Route::get('/kategori-kn/kes/{kes}/sub', [KategoriKnController::class, 'sub'])->name('kategori-kn.sub')->whereNumber('kes');
        Route::post('/kategori-kn/kes/{kes}/sub', [KategoriKnController::class, 'storeSub'])->name('kategori-kn.sub.store')->whereNumber('kes');
        Route::put('/kategori-kn/sub/{sub}', [KategoriKnController::class, 'updateSub'])->name('kategori-kn.sub.update')->whereNumber('sub');
        Route::delete('/kategori-kn/sub/{sub}', [KategoriKnController::class, 'destroySub'])->name('kategori-kn.sub.destroy')->whereNumber('sub');
    });

    // Jawatan (staff job titles)
    Route::middleware('permission:selenggara.jawatan')->group(function () {
        Route::get('/jawatan', [JawatanController::class, 'index'])->name('jawatan.index');
        Route::post('/jawatan', [JawatanController::class, 'store'])->name('jawatan.store');
        Route::put('/jawatan/{jawatan}', [JawatanController::class, 'update'])->name('jawatan.update')->whereNumber('jawatan');
        Route::delete('/jawatan/{jawatan}', [JawatanController::class, 'destroy'])->name('jawatan.destroy')->whereNumber('jawatan');
    });

    // Pengurusan Pengguna
    Route::middleware('permission:urus.pengguna')->group(function () {
        Route::get('/pengguna', [UserController::class, 'index'])->name('pengguna.index');
        Route::get('/pengguna/create', [UserController::class, 'create'])->name('pengguna.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('pengguna.store');
        Route::get('/pengguna/{user}/edit', [UserController::class, 'edit'])->name('pengguna.edit')->whereNumber('user');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('pengguna.update')->whereNumber('user');
        Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->name('pengguna.destroy')->whereNumber('user');
    });

    // Peranan (role) + Akses (permission matrix) — admin-only (permission:urus.peranan)
    Route::middleware('permission:urus.peranan')->group(function () {
        Route::get('/peranan', [RoleController::class, 'index'])->name('peranan.index');
        Route::get('/peranan/create', [RoleController::class, 'create'])->name('peranan.create');
        Route::post('/peranan', [RoleController::class, 'store'])->name('peranan.store');
        Route::get('/peranan/{role}/edit', [RoleController::class, 'edit'])->name('peranan.edit')->whereNumber('role');
        Route::put('/peranan/{role}', [RoleController::class, 'update'])->name('peranan.update')->whereNumber('role');
        Route::delete('/peranan/{role}', [RoleController::class, 'destroy'])->name('peranan.destroy')->whereNumber('role');
        Route::get('/peranan/{role}/akses', [RolePermissionController::class, 'edit'])->name('peranan.akses.edit')->whereNumber('role');
        Route::put('/peranan/{role}/akses', [RolePermissionController::class, 'update'])->name('peranan.akses.update')->whereNumber('role');
    });

    // Audit log
    Route::middleware('permission:audit.view')->group(function () {
        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });

    // Agihan peguam — workload + entry into the 3-tier spine (single-step path retired;
    // the 3-tier spine below is now the sole assignment write path).
    Route::middleware('permission:agihan.manage')->group(function () {
        Route::get('/peguam-panel/beban', [AgihanController::class, 'beban'])->name('agihan.beban');
        Route::post('/agihan/{kes}/masuk', [AgihanSpineController::class, 'masuk'])->name('agihan.masuk')->whereNumber('kes');
    });

    // 3-tier assignment spine (PPUU -> Pengarah -> Ketua Pengarah). Role-gated per action.
    Route::get('/agihan/senarai/{bucket}', [AgihanSpineController::class, 'senarai'])->name('agihan.senarai')->whereIn('bucket', ['baru', 'semasa', 'semula', 'ditolak']);
    Route::get('/agihan/{kes}/maklumat', [AgihanSpineController::class, 'show'])->name('agihan.maklumat')->whereNumber('kes');
    Route::post('/agihan/{kes}/pengarah-terima', [AgihanSpineController::class, 'pengarahTerima'])->name('agihan.pengarah.terima')->whereNumber('kes')->middleware('permission:agihan.pengarah');
    Route::post('/agihan/{kes}/pengarah-tolak', [AgihanSpineController::class, 'pengarahTolak'])->name('agihan.pengarah.tolak')->whereNumber('kes')->middleware('permission:agihan.pengarah');
    Route::post('/agihan/{kes}/ppuu-pilih', [AgihanSpineController::class, 'ppuuPilih'])->name('agihan.ppuu.pilih')->whereNumber('kes')->middleware('permission:agihan.ppuu');
    Route::post('/agihan/{kes}/pengarah-keputusan', [AgihanSpineController::class, 'pengarahKeputusan'])->name('agihan.pengarah.keputusan')->whereNumber('kes')->middleware('permission:agihan.pengarah');
    Route::post('/agihan/{kes}/kp-keputusan', [AgihanSpineController::class, 'kpKeputusan'])->name('agihan.kp.keputusan')->whereNumber('kes')->middleware('permission:agihan.kp');

    // Recovery for Pengarah-rejected new cases (status 9) — re-open or abandon. No more dead-end.
    Route::post('/agihan/{kes}/buka-semula', [AgihanSpineController::class, 'bukaSemula'])->name('agihan.buka-semula')->whereNumber('kes')->middleware('permission:agihan.pengarah');
    Route::post('/agihan/{kes}/batal', [AgihanSpineController::class, 'batalAgihan'])->name('agihan.batal')->whereNumber('kes')->middleware('permission:agihan.pengarah');

    // Tarik Diri Mewakili OYD — staff review queue (PPUU -> Pengarah -> Ketua Pengarah).
    Route::get('/tarik-diri/senarai', [TarikDiriController::class, 'senarai'])->name('tarikdiri.senarai');
    Route::get('/tarik-diri/{kes}/maklumat', [TarikDiriController::class, 'show'])->name('tarikdiri.maklumat')->whereNumber('kes');
    Route::post('/tarik-diri/{kes}/ppuu', [TarikDiriController::class, 'ppuu'])->name('tarikdiri.ppuu')->whereNumber('kes')->middleware('role:ppuu|koordinator|admin');
    Route::post('/tarik-diri/{kes}/pengarah', [TarikDiriController::class, 'pengarah'])->name('tarikdiri.pengarah')->whereNumber('kes')->middleware('role:pengarah|admin');
    Route::post('/tarik-diri/{kes}/kp', [TarikDiriController::class, 'kp'])->name('tarikdiri.kp')->whereNumber('kes')->middleware('role:ketua_pengarah|admin');

    // Kemaskini Bidang Pengkhususan — staff review of lawyer add/drop requests.
    Route::get('/kemaskini-bidang', [KemaskiniBidangController::class, 'index'])->name('kemaskini-bidang.index');
    Route::post('/kemaskini-bidang/{row}/pengarah', [KemaskiniBidangController::class, 'pengarah'])->name('kemaskini-bidang.pengarah')->whereNumber('row')->middleware('role:pengarah|admin');
    Route::post('/kemaskini-bidang/{row}/kp', [KemaskiniBidangController::class, 'kp'])->name('kemaskini-bidang.kp')->whereNumber('row')->middleware('role:ketua_pengarah|admin');
    Route::get('/peguam-panel/{peguam}', [PeguamPanelController::class, 'show'])->name('peguam-panel.show')->whereNumber('peguam');
    Route::get('/peguam-panel/{peguam}/edit', [PeguamPanelController::class, 'edit'])->name('peguam-panel.edit')->whereNumber('peguam');
    Route::put('/peguam-panel/{peguam}', [PeguamPanelController::class, 'update'])->name('peguam-panel.update')->whereNumber('peguam');
    // Lawyer active/inactive lifecycle (deactivation triggers death-redistribution of active cases).
    Route::post('/peguam-panel/{peguam}/nyahaktif', [PeguamPanelController::class, 'nyahaktif'])->name('peguam-panel.nyahaktif')->whereNumber('peguam')->middleware('role:admin|koordinator|pengarah|ketua_pengarah');
    Route::post('/peguam-panel/{peguam}/aktif-semula', [PeguamPanelController::class, 'aktifSemula'])->name('peguam-panel.aktif')->whereNumber('peguam')->middleware('role:admin|koordinator|pengarah|ketua_pengarah');

    // Permohonan peguam panel (application approval workflow)
    Route::get('/permohonan-peguam', [PermohonanPeguamController::class, 'index'])->name('permohonan-peguam.index');
    Route::get('/permohonan-peguam/{butiran}', [PermohonanPeguamController::class, 'show'])->name('permohonan-peguam.show')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/semak', [PermohonanPeguamController::class, 'semak'])->name('permohonan-peguam.semak')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/sokong', [PermohonanPeguamController::class, 'sokong'])->name('permohonan-peguam.sokong')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/keputusan', [PermohonanPeguamController::class, 'keputusan'])->name('permohonan-peguam.keputusan')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/tarik-diri', [PermohonanPeguamController::class, 'tarikDiri'])->name('permohonan-peguam.tarik')->whereNumber('butiran');

    // ---- Lejar Tuntutan Bayaran (central claim ledger) — W15 ----
    // Static segments declared before {tuntutan} (whereNumber) so they never shadow.
    Route::middleware('permission:tuntutan.view')->group(function () {
        Route::get('/lejar-tuntutan', [LejarTuntutanController::class, 'index'])->name('tuntutan.index');
        Route::get('/lejar-tuntutan/eksport', [LejarTuntutanController::class, 'eksport'])->name('tuntutan.eksport');
        Route::get('/lejar-tuntutan/baharu', [LejarTuntutanController::class, 'create'])->name('tuntutan.create')->middleware('permission:tuntutan.manage');
        Route::post('/lejar-tuntutan', [LejarTuntutanController::class, 'store'])->name('tuntutan.store')->middleware('permission:tuntutan.manage');
        Route::get('/lejar-tuntutan/{tuntutan}', [LejarTuntutanController::class, 'show'])->name('tuntutan.show')->whereNumber('tuntutan');
        Route::put('/lejar-tuntutan/{tuntutan}', [LejarTuntutanController::class, 'update'])->name('tuntutan.update')->whereNumber('tuntutan')->middleware('permission:tuntutan.manage');
        Route::post('/lejar-tuntutan/{tuntutan}/hantar', [LejarTuntutanController::class, 'hantar'])->name('tuntutan.hantar')->whereNumber('tuntutan')->middleware('permission:tuntutan.manage');
        Route::post('/lejar-tuntutan/{tuntutan}/semak', [LejarTuntutanController::class, 'semak'])->name('tuntutan.semak')->whereNumber('tuntutan')->middleware('permission:tuntutan.semak');
        Route::post('/lejar-tuntutan/{tuntutan}/lulus', [LejarTuntutanController::class, 'lulus'])->name('tuntutan.lulus')->whereNumber('tuntutan')->middleware('permission:tuntutan.lulus');
        Route::post('/lejar-tuntutan/{tuntutan}/tolak', [LejarTuntutanController::class, 'tolak'])->name('tuntutan.tolak')->whereNumber('tuntutan')->middleware('permission:tuntutan.lulus');
        Route::post('/lejar-tuntutan/{tuntutan}/bayar', [LejarTuntutanController::class, 'bayar'])->name('tuntutan.bayar')->whereNumber('tuntutan')->middleware('permission:tuntutan.bayar');
    });

    // ---- Pembelaan Awam (public criminal defence) register — W9 ----
    // Tagged forms rows (D3); assignment/closure reuse the shared agihan spine.
    // Static /baharu declared before {kes} (whereNumber) so it never shadows.
    Route::middleware('permission:pembelaan.view')->group(function () {
        Route::get('/pembelaan-awam', [PembelaanAwamController::class, 'index'])->name('pembelaan.index');
        Route::get('/pembelaan-awam/baharu', [PembelaanAwamController::class, 'create'])->name('pembelaan.create')->middleware('permission:pembelaan.manage');
        Route::post('/pembelaan-awam', [PembelaanAwamController::class, 'store'])->name('pembelaan.store')->middleware('permission:pembelaan.manage');
        Route::get('/pembelaan-awam/{kes}', [PembelaanAwamController::class, 'show'])->name('pembelaan.show')->whereNumber('kes');

        // W14 — legal-aid certificate (Perakuan Bantuan Guaman) issue/finalise.
        Route::post('/pembelaan-awam/{kes}/perakuan/interim', [PembelaanAwamController::class, 'keluarInterim'])->name('pembelaan.perakuan.interim')->whereNumber('kes')->middleware('permission:kes.perakuan');
        Route::post('/pembelaan-awam/{kes}/perakuan/muktamad', [PembelaanAwamController::class, 'muktamad'])->name('pembelaan.perakuan.muktamad')->whereNumber('kes')->middleware('permission:kes.perakuan');
    });

    // ---- Khidmat Nasihat (legal-advisory applications) — batch 9 ----
    // Slice 1: list/show (read-only), gated khidmat.view.
    Route::middleware('permission:khidmat.view')->group(function () {
        Route::get('/khidmat-nasihat', [KhidmatNasihatController::class, 'index'])->name('khidmat.index');
        Route::get('/khidmat-nasihat/{khidmat}', [KhidmatNasihatController::class, 'show'])->name('khidmat.show')->whereNumber('khidmat');
    });

    // Slice 2: staff-driven create wizard + DRAF edit (slot booking + payment), gated khidmat.manage.
    Route::middleware('permission:khidmat.manage')->group(function () {
        // Slice 3: eligibility 3-modal screening gate — clear before the wizard opens.
        // (show uses whereNumber, so these non-numeric static paths are not shadowed.)
        Route::get('/khidmat-nasihat/saringan', [KhidmatNasihatController::class, 'saringan'])->name('khidmat.saringan');
        Route::post('/khidmat-nasihat/saringan', [KhidmatNasihatController::class, 'saringanSemak'])->name('khidmat.saringan.semak');

        Route::get('/khidmat-nasihat/baharu', [KhidmatNasihatController::class, 'create'])->name('khidmat.create');
        Route::post('/khidmat-nasihat', [KhidmatNasihatController::class, 'store'])->name('khidmat.store');
        Route::get('/khidmat-nasihat/{khidmat}/kemaskini', [KhidmatNasihatController::class, 'edit'])->name('khidmat.edit')->whereNumber('khidmat');
        Route::put('/khidmat-nasihat/{khidmat}', [KhidmatNasihatController::class, 'update'])->name('khidmat.update')->whereNumber('khidmat');
    });

    // ==== BATCH 10 SLICE 3 (kalendar): Cuti Negeri CRUD + Jadual Janji Temu ====
    // Cuti Negeri (ref_cuti) — state-specific public holidays (subset of 16 states),
    // sharing the CutiNegeri bitmask + permission:selenggara.cuti with Cuti Umum.
    Route::middleware('permission:selenggara.cuti')->group(function () {
        Route::get('/cuti-negeri', [CutiNegeriController::class, 'index'])->name('cuti-negeri.index');
        Route::get('/cuti-negeri/create', [CutiNegeriController::class, 'create'])->name('cuti-negeri.create');
        Route::post('/cuti-negeri', [CutiNegeriController::class, 'store'])->name('cuti-negeri.store');
        Route::get('/cuti-negeri/{cuti}/edit', [CutiNegeriController::class, 'edit'])->name('cuti-negeri.edit')->whereNumber('cuti');
        Route::put('/cuti-negeri/{cuti}', [CutiNegeriController::class, 'update'])->name('cuti-negeri.update')->whereNumber('cuti');
        Route::delete('/cuti-negeri/{cuti}', [CutiNegeriController::class, 'destroy'])->name('cuti-negeri.destroy')->whereNumber('cuti');
    });

    // Jadual Janji Temu — read-only month calendar of booked temu_janji per cawangan,
    // honoring weekends/holidays/closures via SlotAvailabilityService. Gated slot.view.
    Route::middleware('permission:slot.view')->group(function () {
        Route::get('/jadual-janji-temu', [JadualJanjiTemuController::class, 'index'])->name('jadual.index');
    });
    // ==== END BATCH 10 SLICE 3 ====

    // ==== BATCH 11 SLICES A+B: Khidmat Nasihat officer processing ====
    // Slice A: branch-scoped officer worklist + filters + dashboard count tiles.
    // Slice B: assign PKN officer (BAHARU->DALAM_PROSES) + pengesahan janji temu
    // (accept/reject/attendance/complete). All gated permission:khidmat.proses.
    Route::middleware('permission:khidmat.proses')->group(function () {
        Route::get('/khidmat-proses', [KhidmatProsesController::class, 'index'])->name('khidmat.proses.index');
        Route::post('/khidmat-proses/{khidmat}/agih', [KhidmatProsesController::class, 'assign'])->name('khidmat.proses.assign')->whereNumber('khidmat');
        Route::post('/khidmat-proses/{khidmat}/temu/terima', [KhidmatProsesController::class, 'terima'])->name('khidmat.proses.temu.terima')->whereNumber('khidmat');
        Route::post('/khidmat-proses/{khidmat}/temu/tolak', [KhidmatProsesController::class, 'tolak'])->name('khidmat.proses.temu.tolak')->whereNumber('khidmat');
        Route::post('/khidmat-proses/{khidmat}/temu/kehadiran', [KhidmatProsesController::class, 'kehadiran'])->name('khidmat.proses.temu.kehadiran')->whereNumber('khidmat');
        Route::post('/khidmat-proses/{khidmat}/temu/selesai', [KhidmatProsesController::class, 'selesai'])->name('khidmat.proses.temu.selesai')->whereNumber('khidmat');
        // Slice C: KN -> forms case bridge ("Buka Kes") — open a litigation case from a SELESAI KN.
        Route::post('/khidmat-proses/{khidmat}/buka-kes', [KhidmatProsesController::class, 'bukaKes'])->name('khidmat.proses.buka-kes')->whereNumber('khidmat');
    });
    // ==== END BATCH 11 SLICES A+B ====

    // ==== BATCH 12 — LAPORAN KHIDMAT NASIHAT (8 statistical reports) ====
    // 8 KN reports (detail + bucket-aggregate + month pivots), all reusing the
    // existing permission:laporan.view. Branch isolation is applied explicitly
    // (KN has no CawanganScope) inside LaporanKnService. Detail reports (Pandangan
    // UU, Pendaftaran) export to .xlsx via maatwebsite; print via the view's CSS.
    Route::middleware('permission:laporan.view')->prefix('laporan-kn')->name('laporan-kn.')->group(function () {
        Route::get('/', [LaporanKhidmatNasihatController::class, 'index'])->name('index');

        // 1. Pandangan Undang-Undang (detail + Excel)
        Route::get('/pandangan-uu', [LaporanKhidmatNasihatController::class, 'pandanganUu'])->name('pandangan-uu');
        Route::get('/pandangan-uu/excel', [LaporanKhidmatNasihatController::class, 'pandanganUuExcel'])->name('pandangan-uu.excel');

        // 2. Cara Mengetahui JBG (pie + table)
        Route::get('/cara-mengetahui', [LaporanKhidmatNasihatController::class, 'caraMengetahui'])->name('cara-mengetahui');

        // 3. Mengikut Cawangan (stacked bar + table)
        Route::get('/mengikut-cawangan', [LaporanKhidmatNasihatController::class, 'mengikutCawangan'])->name('mengikut-cawangan');

        // 4. Mengikut Kategori Kes (stacked bar + table)
        Route::get('/mengikut-kategori', [LaporanKhidmatNasihatController::class, 'mengikutKategori'])->name('mengikut-kategori');

        // 5. Mengikut Sub Kategori (table only)
        Route::get('/mengikut-subkategori', [LaporanKhidmatNasihatController::class, 'mengikutSubkategori'])->name('mengikut-subkategori');

        // 6. Pendaftaran Khidmat Nasihat (detail + Excel)
        Route::get('/pendaftaran', [LaporanKhidmatNasihatController::class, 'pendaftaran'])->name('pendaftaran');
        Route::get('/pendaftaran/excel', [LaporanKhidmatNasihatController::class, 'pendaftaranExcel'])->name('pendaftaran.excel');

        // 7. Tahap Kepuasan Pelanggan (pie + table)
        Route::get('/kepuasan', [LaporanKhidmatNasihatController::class, 'kepuasan'])->name('kepuasan');

        // 8. Mengikut Kaum/Jantina (stacked bar + table)
        Route::get('/kaum-jantina', [LaporanKhidmatNasihatController::class, 'kaumJantina'])->name('kaum-jantina');
    });
    // ==== END BATCH 12 ====
});

// ---- Lawyer area: panel lawyers (peguam) ----
Route::middleware(['auth', 'permission:lawyer.area'])->prefix('peguam')->group(function () {
    Route::get('/', [PeguamController::class, 'dashboard'])->name('peguam.dashboard');
    Route::get('/kes', [PeguamController::class, 'kes'])->name('peguam.kes');
    Route::get('/tawaran', [PeguamController::class, 'tawaran'])->name('peguam.tawaran');
    Route::get('/profil', [PeguamController::class, 'profil'])->name('peguam.profil');
    Route::get('/profil/kemaskini', [PeguamController::class, 'editProfil'])->name('peguam.profil.edit');
    Route::post('/profil/kemaskini', [PeguamController::class, 'updateProfil'])->name('peguam.profil.update');

    // Offer accept/reject (tawaran) + lawyer-side case reporting.
    Route::post('/kes/{kes}/terima', [PeguamController::class, 'terima'])->name('peguam.terima')->whereNumber('kes');
    Route::post('/kes/{kes}/tolak', [PeguamController::class, 'tolak'])->name('peguam.tolak')->whereNumber('kes');
    Route::get('/kes/{kes}', [PeguamController::class, 'kesShow'])->name('peguam.kes.show')->whereNumber('kes');
    Route::post('/kes/{kes}/laporan', [PeguamController::class, 'storeLaporan'])->name('peguam.laporan')->whereNumber('kes');
    Route::post('/kes/{kes}/selesai', [PeguamController::class, 'selesai'])->name('peguam.selesai')->whereNumber('kes');

    // Tarik Diri Mewakili OYD (lawyer-initiated withdrawal from an assigned case).
    Route::get('/kes/{kes}/tarik-diri', [PeguamController::class, 'tarikDiriForm'])->name('peguam.tarikdiri.form')->whereNumber('kes');
    Route::post('/kes/{kes}/tarik-diri', [PeguamController::class, 'tarikDiriStore'])->name('peguam.tarikdiri.store')->whereNumber('kes');

    // Bidang Pengkhususan add/drop requests (lawyer-initiated).
    Route::post('/pengkhususan/tambah', [PeguamController::class, 'pengkhususanAdd'])->name('peguam.pengkhususan.add');
    Route::post('/pengkhususan/{row}/gugur', [PeguamController::class, 'pengkhususanDrop'])->name('peguam.pengkhususan.drop')->whereNumber('row');

    // Lejar Tuntutan — lawyer self-service (W15). File claims against assigned cases.
    Route::get('/tuntutan', [PeguamTuntutanController::class, 'index'])->name('peguam.tuntutan.index');
    Route::get('/tuntutan/{tuntutan}', [PeguamTuntutanController::class, 'show'])->name('peguam.tuntutan.show')->whereNumber('tuntutan');
    Route::get('/kes/{kes}/tuntutan/baharu', [PeguamTuntutanController::class, 'create'])->name('peguam.tuntutan.create')->whereNumber('kes');
    Route::post('/kes/{kes}/tuntutan', [PeguamTuntutanController::class, 'store'])->name('peguam.tuntutan.store')->whereNumber('kes');
});

// ==== BATCH 12 — MAKLUM BALAS (public) ====
// Post-appointment satisfaction feedback. PUBLIC (no auth) per locked decision —
// citizen opens the link after a SELESAI advisory appointment; no login. One
// feedback per KN (DB-unique + app guard). Throttled (6/min) for light anti-abuse.
Route::get('/maklum-balas/{no_permohonan}', [MaklumBalasController::class, 'show'])
    ->middleware('throttle:6,1')
    ->name('maklum-balas.show');
Route::post('/maklum-balas/{no_permohonan}', [MaklumBalasController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('maklum-balas.store');
// ==== END BATCH 12 ====
