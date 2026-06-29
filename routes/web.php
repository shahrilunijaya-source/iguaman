<?php

use App\Http\Controllers\AgihanController;
use App\Http\Controllers\AgihanSpineController;
use App\Http\Controllers\TarikDiriController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\CetakanController;
use App\Http\Controllers\KeputusanController;
use App\Http\Controllers\KesController;
use App\Http\Controllers\KpiController;
use App\Http\Controllers\LampiranController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\OydController;
use App\Http\Controllers\MahkamahController;
use App\Http\Controllers\MahkamahRefController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PosterController;
use App\Http\Controllers\RefKesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PengantaraanController;
use App\Http\Controllers\PeguamController;
use App\Http\Controllers\PeguamDaftarController;
use App\Http\Controllers\PeguamPanelController;
use App\Http\Controllers\PermohonanPeguamController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\SystemAuthController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

// Public landing.
Route::get('/', fn () => view('welcome'))->name('home');

// Public lawyer panel application (no login — prospective panel lawyers). Throttled + honeypot.
Route::get('/peguam/daftar', [PeguamDaftarController::class, 'create'])->name('peguam.daftar');
Route::post('/peguam/daftar', [PeguamDaftarController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('peguam.daftar.store');

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
Route::middleware(['auth', 'role:admin,pengarah,koordinator,pegawai,ppuu,pembantu_tadbir,ketua_pengarah'])->group(function () {
    Route::get('/system', [SystemController::class, 'utama'])->name('system.utama');

    // Rekod kes (Case backbone + permohonan CRUD)
    Route::get('/kes', [KesController::class, 'index'])->name('kes.index');
    Route::get('/fail-tutup', [KesController::class, 'tutup'])->name('kes.tutup');
    Route::get('/kes/create', [KesController::class, 'create'])->name('kes.create');
    Route::post('/kes', [KesController::class, 'store'])->name('kes.store');
    Route::get('/kes/{kes}/edit', [KesController::class, 'edit'])->name('kes.edit')->whereNumber('kes');
    Route::put('/kes/{kes}', [KesController::class, 'update'])->name('kes.update')->whereNumber('kes');
    Route::get('/kes/{kes}', [KesController::class, 'show'])->name('kes.show')->whereNumber('kes');

    // Keputusan Pengarah (peringkat 2 approve/reject) + Tutup Fail (peringkat 7) — gated in controller
    Route::post('/kes/{kes}/lulus', [KeputusanController::class, 'lulus'])->name('kes.lulus')->whereNumber('kes');
    Route::post('/kes/{kes}/tolak', [KeputusanController::class, 'tolak'])->name('kes.tolak')->whereNumber('kes');
    Route::post('/kes/{kes}/tutup-fail', [KeputusanController::class, 'tutupFail'])->name('kes.tutupfail')->whereNumber('kes');

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
    Route::get('/laporan/{type}', [LaporanController::class, 'show'])->name('laporan.show')->whereIn('type', $laporanTypes);

    // Statistik + exports
    Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik.index');
    Route::get('/statistik/excel', [StatistikController::class, 'excel'])->name('statistik.excel');
    Route::get('/statistik/pdf', [StatistikController::class, 'pdf'])->name('statistik.pdf');

    // Selenggara (maintenance) + Pegawai JBG registry + Audit log — supervisory roles only
    Route::middleware('role:admin,pengarah,koordinator,ketua_pengarah')->group(function () {
        Route::get('/pegawai', [PegawaiController::class, 'index'])->name('pegawai.index');
        Route::get('/pegawai/create', [PegawaiController::class, 'create'])->name('pegawai.create');
        Route::post('/pegawai', [PegawaiController::class, 'store'])->name('pegawai.store');
        Route::get('/pegawai/{pegawai}/edit', [PegawaiController::class, 'edit'])->name('pegawai.edit')->whereNumber('pegawai');
        Route::put('/pegawai/{pegawai}', [PegawaiController::class, 'update'])->name('pegawai.update')->whereNumber('pegawai');
        Route::delete('/pegawai/{pegawai}', [PegawaiController::class, 'destroy'])->name('pegawai.destroy')->whereNumber('pegawai');

        // e-Poster
        Route::get('/poster', [PosterController::class, 'index'])->name('poster.index');
        Route::get('/poster/create', [PosterController::class, 'create'])->name('poster.create');
        Route::post('/poster', [PosterController::class, 'store'])->name('poster.store');
        Route::get('/poster/{poster}/edit', [PosterController::class, 'edit'])->name('poster.edit')->whereNumber('poster');
        Route::put('/poster/{poster}', [PosterController::class, 'update'])->name('poster.update')->whereNumber('poster');
        Route::delete('/poster/{poster}', [PosterController::class, 'destroy'])->name('poster.destroy')->whereNumber('poster');

        // Jenis Kes (ref_kes)
        Route::get('/ref-kes', [RefKesController::class, 'index'])->name('ref-kes.index');
        Route::get('/ref-kes/create', [RefKesController::class, 'create'])->name('ref-kes.create');
        Route::post('/ref-kes', [RefKesController::class, 'store'])->name('ref-kes.store');
        Route::get('/ref-kes/{ref_kes}/edit', [RefKesController::class, 'edit'])->name('ref-kes.edit')->whereNumber('ref_kes');
        Route::put('/ref-kes/{ref_kes}', [RefKesController::class, 'update'])->name('ref-kes.update')->whereNumber('ref_kes');
        Route::delete('/ref-kes/{ref_kes}', [RefKesController::class, 'destroy'])->name('ref-kes.destroy')->whereNumber('ref_kes');

        // Mahkamah reference (sivil + syariah)
        Route::get('/mahkamah-ref/{jenis}', [MahkamahRefController::class, 'index'])->name('mahkamah-ref.index')->whereIn('jenis', ['sivil', 'syariah']);
        Route::get('/mahkamah-ref/{jenis}/create', [MahkamahRefController::class, 'create'])->name('mahkamah-ref.create')->whereIn('jenis', ['sivil', 'syariah']);
        Route::post('/mahkamah-ref/{jenis}', [MahkamahRefController::class, 'store'])->name('mahkamah-ref.store')->whereIn('jenis', ['sivil', 'syariah']);
        Route::get('/mahkamah-ref/{jenis}/{id}/edit', [MahkamahRefController::class, 'edit'])->name('mahkamah-ref.edit')->whereIn('jenis', ['sivil', 'syariah'])->whereNumber('id');
        Route::put('/mahkamah-ref/{jenis}/{id}', [MahkamahRefController::class, 'update'])->name('mahkamah-ref.update')->whereIn('jenis', ['sivil', 'syariah'])->whereNumber('id');
        Route::delete('/mahkamah-ref/{jenis}/{id}', [MahkamahRefController::class, 'destroy'])->name('mahkamah-ref.destroy')->whereIn('jenis', ['sivil', 'syariah'])->whereNumber('id');

        // Pengurusan Pengguna
        Route::get('/pengguna', [UserController::class, 'index'])->name('pengguna.index');
        Route::get('/pengguna/create', [UserController::class, 'create'])->name('pengguna.create');
        Route::post('/pengguna', [UserController::class, 'store'])->name('pengguna.store');
        Route::get('/pengguna/{user}/edit', [UserController::class, 'edit'])->name('pengguna.edit')->whereNumber('user');
        Route::put('/pengguna/{user}', [UserController::class, 'update'])->name('pengguna.update')->whereNumber('user');
        Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->name('pengguna.destroy')->whereNumber('user');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
    });

    // Agihan peguam (assignment) + workload
    Route::get('/kes/{kes}/agih', [AgihanController::class, 'form'])->name('agihan.form')->whereNumber('kes');
    Route::post('/kes/{kes}/agih', [AgihanController::class, 'store'])->name('agihan.store')->whereNumber('kes');
    Route::get('/peguam-panel/beban', [AgihanController::class, 'beban'])->name('agihan.beban');

    // 3-tier assignment spine (PPUU -> Pengarah -> Ketua Pengarah). Role-gated per action.
    Route::get('/agihan/senarai/{bucket}', [AgihanSpineController::class, 'senarai'])->name('agihan.senarai')->whereIn('bucket', ['baru', 'semasa', 'semula']);
    Route::get('/agihan/{kes}/maklumat', [AgihanSpineController::class, 'show'])->name('agihan.maklumat')->whereNumber('kes');
    Route::post('/agihan/{kes}/pengarah-terima', [AgihanSpineController::class, 'pengarahTerima'])->name('agihan.pengarah.terima')->whereNumber('kes')->middleware('role:pengarah,admin');
    Route::post('/agihan/{kes}/pengarah-tolak', [AgihanSpineController::class, 'pengarahTolak'])->name('agihan.pengarah.tolak')->whereNumber('kes')->middleware('role:pengarah,admin');
    Route::post('/agihan/{kes}/ppuu-pilih', [AgihanSpineController::class, 'ppuuPilih'])->name('agihan.ppuu.pilih')->whereNumber('kes')->middleware('role:ppuu,koordinator,admin');
    Route::post('/agihan/{kes}/pengarah-keputusan', [AgihanSpineController::class, 'pengarahKeputusan'])->name('agihan.pengarah.keputusan')->whereNumber('kes')->middleware('role:pengarah,admin');
    Route::post('/agihan/{kes}/kp-keputusan', [AgihanSpineController::class, 'kpKeputusan'])->name('agihan.kp.keputusan')->whereNumber('kes')->middleware('role:ketua_pengarah,admin');

    // Tarik Diri Mewakili OYD — staff review queue (PPUU -> Pengarah -> Ketua Pengarah).
    Route::get('/tarik-diri/senarai', [TarikDiriController::class, 'senarai'])->name('tarikdiri.senarai');
    Route::get('/tarik-diri/{kes}/maklumat', [TarikDiriController::class, 'show'])->name('tarikdiri.maklumat')->whereNumber('kes');
    Route::post('/tarik-diri/{kes}/ppuu', [TarikDiriController::class, 'ppuu'])->name('tarikdiri.ppuu')->whereNumber('kes')->middleware('role:ppuu,koordinator,admin');
    Route::post('/tarik-diri/{kes}/pengarah', [TarikDiriController::class, 'pengarah'])->name('tarikdiri.pengarah')->whereNumber('kes')->middleware('role:pengarah,admin');
    Route::post('/tarik-diri/{kes}/kp', [TarikDiriController::class, 'kp'])->name('tarikdiri.kp')->whereNumber('kes')->middleware('role:ketua_pengarah,admin');
    Route::get('/peguam-panel/{peguam}', [PeguamPanelController::class, 'show'])->name('peguam-panel.show')->whereNumber('peguam');
    Route::get('/peguam-panel/{peguam}/edit', [PeguamPanelController::class, 'edit'])->name('peguam-panel.edit')->whereNumber('peguam');
    Route::put('/peguam-panel/{peguam}', [PeguamPanelController::class, 'update'])->name('peguam-panel.update')->whereNumber('peguam');

    // Permohonan peguam panel (application approval workflow)
    Route::get('/permohonan-peguam', [PermohonanPeguamController::class, 'index'])->name('permohonan-peguam.index');
    Route::get('/permohonan-peguam/{butiran}', [PermohonanPeguamController::class, 'show'])->name('permohonan-peguam.show')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/semak', [PermohonanPeguamController::class, 'semak'])->name('permohonan-peguam.semak')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/sokong', [PermohonanPeguamController::class, 'sokong'])->name('permohonan-peguam.sokong')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/keputusan', [PermohonanPeguamController::class, 'keputusan'])->name('permohonan-peguam.keputusan')->whereNumber('butiran');
    Route::post('/permohonan-peguam/{butiran}/tarik-diri', [PermohonanPeguamController::class, 'tarikDiri'])->name('permohonan-peguam.tarik')->whereNumber('butiran');
});

// ---- Lawyer area: panel lawyers (peguam) ----
Route::middleware(['auth', 'role:peguam'])->prefix('peguam')->group(function () {
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

    // Tarik Diri Mewakili OYD (lawyer-initiated withdrawal from an assigned case).
    Route::get('/kes/{kes}/tarik-diri', [PeguamController::class, 'tarikDiriForm'])->name('peguam.tarikdiri.form')->whereNumber('kes');
    Route::post('/kes/{kes}/tarik-diri', [PeguamController::class, 'tarikDiriStore'])->name('peguam.tarikdiri.store')->whereNumber('kes');
});
