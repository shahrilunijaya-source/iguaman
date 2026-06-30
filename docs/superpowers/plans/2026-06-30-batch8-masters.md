# Batch 8 — Foundations / Reference Masters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Build the reference masters the advisory/appointment subsystem consumes — `cawangan_jbg`(+`bilik`), `cawangan_jkm`, `cawangan_penjara`, the 3-level `kn_kategori`→`kn_kategori_kes`→`kn_subkategori` tree, and `ref_jawatan` — with seed data, Tetapan CRUD UI, and RBAC gating.

**Architecture:** Greenfield Laravel Blueprint migrations (int PKs to match `ref_negeri`), Eloquent models with `belongsTo RefNegeri`, thin controllers (RefKesController style), Blade CRUD (tap/wiz/btn conventions), seeders that derive from existing 2in1 tables (`ref_kes`, `pegawai_jbg`, live `cawangan` strings) + 23 literal branch rows. Gated by 3 new Spatie permissions.

**Tech Stack:** Laravel 13.8, PHP 8.3, spatie/laravel-permission ^7, PHPUnit 12.5. Feature tests run against live `iguaman_2in1` MySQL (repo convention), self-cleaning by tag/prefix.

**Spec:** `docs/superpowers/specs/2026-06-30-batch8-masters-design.md`. **Seed data:** `scratchpad/batch8-seed.txt`. **Prereq:** batch 7 (RBAC) merged to `main`; branch from `main` AFTER concurrent EPIC F/G settles.

---

## Pre-flight (do first, before Task 1)
- [ ] Branch from current `main`: `git checkout main && git pull && git checkout -b batch-8-masters`.
- [ ] Confirm baseline perm count: `php artisan tinker --execute="echo \Spatie\Permission\Models\Permission::count();"` — record N (expected 33; if higher, concurrent work added more). Task 3 adds 3 → N+3.
- [ ] Confirm `ref_kes`, `ref_negeri`, `pegawai_jbg` exist + have rows (sources for seeders).

## File structure
**Migrations (8):** `create_cawangan_jbg_table`, `create_bilik_table`, `create_cawangan_jkm_table`, `create_cawangan_penjara_table`, `create_kn_kategori_table`, `create_kn_kategori_kes_table`, `create_kn_subkategori_table`, `create_ref_jawatan_table`.
**Models (8):** `CawanganJbg`, `Bilik`, `CawanganJkm`, `CawanganPenjara`, `KnKategori`, `KnKategoriKes`, `KnSubkategori`, `RefJawatan`.
**Controllers (5):** `CawanganJbgController` (+bilik actions), `CawanganJkmController`, `CawanganPenjaraController`, `KnKategoriController` (+ kes/subkategori), `RefJawatanController`.
**Seeder:** `Batch8MasterSeeder` (+ wire into `DatabaseSeeder`). **RBAC:** edit `RolePermissionSeeder`.
**Views:** `cawangan-jbg/`, `cawangan-jkm/`, `cawangan-penjara/`, `kn-kategori/`, `jawatan/`. **Routes:** gated groups in `web.php`. **Sidebar:** `layouts/staff.blade.php`.
**Tests:** `Batch8MasterCrudTest`, `Batch8SeedTest`, + `Batch7SeederTest` count bump.

---

## Task 1: Cawangan-family migrations + models

**Files:**
- Create: `database/migrations/2026_07_01_000001_create_cawangan_jbg_table.php`, `..._000002_create_bilik_table.php`, `..._000003_create_cawangan_jkm_table.php`, `..._000004_create_cawangan_penjara_table.php`
- Create: `app/Models/CawanganJbg.php`, `Bilik.php`, `CawanganJkm.php`, `CawanganPenjara.php`

- [ ] **Step 1: Write the migrations**

`create_cawangan_jbg_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cawangan_jbg', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kod', 50)->unique();          // = scope key (users.cawangan / forms.cawangan)
            $table->string('nama');
            $table->unsignedInteger('negeri_id')->nullable()->index();
            $table->string('hari_minggu', 20)->nullable(); // weekend config for slot engine (batch 10)
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cawangan_jbg'); }
};
```
`create_bilik_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bilik', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('cawangan_jbg_id')->index();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('bilik'); }
};
```
`create_cawangan_jkm_table.php` (and `create_cawangan_penjara_table.php` — identical, swap `cawangan_jkm`→`cawangan_penjara`):
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cawangan_jkm', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kod', 50)->unique();
            $table->string('nama');
            $table->unsignedInteger('negeri_id')->nullable()->index();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cawangan_jkm'); }
};
```

- [ ] **Step 2: Write the models**

`app/Models/CawanganJbg.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CawanganJbg extends Model
{
    protected $table = 'cawangan_jbg';
    protected $guarded = ['id'];
    protected $casts = ['aktif' => 'boolean'];

    public function negeri(): BelongsTo { return $this->belongsTo(RefNegeri::class, 'negeri_id'); }
    public function bilik(): HasMany { return $this->hasMany(Bilik::class, 'cawangan_jbg_id'); }
}
```
`app/Models/Bilik.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bilik extends Model
{
    protected $table = 'bilik';
    protected $guarded = ['id'];
    protected $casts = ['aktif' => 'boolean'];

    public function cawanganJbg(): BelongsTo { return $this->belongsTo(CawanganJbg::class, 'cawangan_jbg_id'); }
}
```
`app/Models/CawanganJkm.php` (and `CawanganPenjara.php` — swap table/class):
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CawanganJkm extends Model
{
    protected $table = 'cawangan_jkm';
    protected $guarded = ['id'];
    protected $casts = ['aktif' => 'boolean'];

    public function negeri(): BelongsTo { return $this->belongsTo(RefNegeri::class, 'negeri_id'); }
}
```

- [ ] **Step 3: Migrate**

Run: `php artisan migrate`
Expected: 4 tables created. Verify: `php artisan tinker --execute="foreach(['cawangan_jbg','bilik','cawangan_jkm','cawangan_penjara'] as \$t){echo \$t.':'.(Schema::hasTable(\$t)?'ok':'MISSING').' ';}"` → all ok.

- [ ] **Step 4: Commit**
```bash
git add database/migrations/2026_07_01_00000[1-4]_* app/Models/CawanganJbg.php app/Models/Bilik.php app/Models/CawanganJkm.php app/Models/CawanganPenjara.php
git commit -m "feat(batch8): cawangan-family migrations + models (jbg/bilik/jkm/penjara)"
```

---

## Task 2: Kategori-tree + jawatan migrations + models

**Files:**
- Create: `..._000005_create_kn_kategori_table.php`, `..._000006_create_kn_kategori_kes_table.php`, `..._000007_create_kn_subkategori_table.php`, `..._000008_create_ref_jawatan_table.php`
- Create: `app/Models/KnKategori.php`, `KnKategoriKes.php`, `KnSubkategori.php`, `RefJawatan.php`

- [ ] **Step 1: Migrations**

`create_kn_kategori_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kn_kategori', function (Blueprint $table) {
            $table->increments('id');
            $table->string('kod', 10)->nullable();   // legacy jenis code SIV/SYA/JEN/PG
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('kn_kategori'); }
};
```
`create_kn_kategori_kes_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kn_kategori_kes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kategori_id')->index();
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('kn_kategori_kes'); }
};
```
`create_kn_subkategori_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kn_subkategori', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('kategori_kes_id')->index();
            $table->string('kod', 20)->nullable();  // = ref_kes.id_kes
            $table->string('nama', 500);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('kn_subkategori'); }
};
```
`create_ref_jawatan_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ref_jawatan', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama');
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('ref_jawatan'); }
};
```

- [ ] **Step 2: Models**

`KnKategori.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnKategori extends Model
{
    protected $table = 'kn_kategori';
    protected $guarded = ['id'];
    protected $casts = ['aktif' => 'boolean'];

    public function kategoriKes(): HasMany { return $this->hasMany(KnKategoriKes::class, 'kategori_id'); }
}
```
`KnKategoriKes.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KnKategoriKes extends Model
{
    protected $table = 'kn_kategori_kes';
    protected $guarded = ['id'];
    protected $casts = ['aktif' => 'boolean'];

    public function kategori(): BelongsTo { return $this->belongsTo(KnKategori::class, 'kategori_id'); }
    public function subkategori(): HasMany { return $this->hasMany(KnSubkategori::class, 'kategori_kes_id'); }
}
```
`KnSubkategori.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnSubkategori extends Model
{
    protected $table = 'kn_subkategori';
    protected $guarded = ['id'];
    protected $casts = ['aktif' => 'boolean'];

    public function kategoriKes(): BelongsTo { return $this->belongsTo(KnKategoriKes::class, 'kategori_kes_id'); }
}
```
`RefJawatan.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RefJawatan extends Model
{
    protected $table = 'ref_jawatan';
    protected $guarded = ['id'];
    protected $casts = ['aktif' => 'boolean'];
}
```

- [ ] **Step 3: Migrate + verify**

Run: `php artisan migrate`
Verify: `php artisan tinker --execute="foreach(['kn_kategori','kn_kategori_kes','kn_subkategori','ref_jawatan'] as \$t){echo \$t.':'.(Schema::hasTable(\$t)?'ok':'MISSING').' ';}"` → all ok.

- [ ] **Step 4: Commit**
```bash
git add database/migrations/2026_07_01_00000[5-8]_* app/Models/KnKategori.php app/Models/KnKategoriKes.php app/Models/KnSubkategori.php app/Models/RefJawatan.php
git commit -m "feat(batch8): kategori-tree + jawatan migrations + models"
```

---

## Task 3: RBAC permissions (+3)

**Files:**
- Modify: `database/seeders/RolePermissionSeeder.php`
- Modify: `tests/Feature/Batch7SeederTest.php` (count bump)

- [ ] **Step 1: Read the live seeder first**

Run: `php artisan tinker --execute="echo \Spatie\Permission\Models\Permission::count();"` — record current count C (the test must assert C+3). Open `RolePermissionSeeder.php` and confirm the current MATRIX (it may already contain `selenggara.cuti` from concurrent work — do NOT remove anything).

- [ ] **Step 2: Add 3 permissions to the MATRIX**

In `RolePermissionSeeder::MATRIX`, add these entries (granted to the supervisory set, matching the other `selenggara.*`):
```php
        'selenggara.cawangan'    => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.kategori'    => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.jawatan'     => ['pengarah', 'koordinator', 'ketua_pengarah'],
```

- [ ] **Step 3: Re-run seeder + verify count**

Run: `php artisan db:seed --class=RolePermissionSeeder && php artisan tinker --execute="echo \Spatie\Permission\Models\Permission::count();"`
Expected: C+3. Verify grant: `php artisan tinker --execute="echo \Spatie\Permission\Models\Role::findByName('pengarah','web')->hasPermissionTo('selenggara.cawangan')?'y':'n';"` → y.

- [ ] **Step 4: Bump Batch7SeederTest count**

In `tests/Feature/Batch7SeederTest.php`, update `test_all_roles_and_permissions_exist` — change the asserted permission count from its current value to **C+3** (use the number recorded in Step 1).
Run: `php artisan test --filter=Batch7SeederTest` → green.

- [ ] **Step 5: Commit**
```bash
git add database/seeders/RolePermissionSeeder.php tests/Feature/Batch7SeederTest.php
git commit -m "feat(batch8): RBAC perms selenggara.cawangan/kategori/jawatan"
```

---

## Task 4: Seeders (derive from ref_kes / live / pegawai_jbg + 23 branches)

**Files:**
- Create: `database/seeders/Batch8MasterSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Batch8SeedTest.php`

- [ ] **Step 1: Write the seeder**

`database/seeders/Batch8MasterSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Bilik;
use App\Models\CawanganJbg;
use App\Models\KnKategori;
use App\Models\KnKategoriKes;
use App\Models\KnSubkategori;
use App\Models\RefJawatan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Batch-8 reference masters. Derives the kategori tree from existing ref_kes,
 * cawangan_jbg from 23 legacy branches + live distinct scope values, jawatan from
 * pegawai_jbg. jkm/penjara/bilik ship empty (no source data). Idempotent.
 */
class Batch8MasterSeeder extends Seeder
{
    private const JENIS = ['SIV' => 'Sivil', 'SYA' => 'Syariah', 'JEN' => 'Jenayah', 'PG' => 'Pendamping Guaman'];

    /** 23 JBG branches: [kod, nama, negeri_nama]. MIRI negeri = SARAWAK. */
    private const CAWANGAN_JBG = [
        ['01021011', 'JBG JOHOR', 'JOHOR'],
        ['01061011', 'JBG MUAR', 'JOHOR'],
        ['02031011', 'JBG KEDAH', 'KEDAH'],
        ['02071011', 'JBG LANGKAWI', 'KEDAH'],
        ['03021011', 'JBG KELANTAN', 'KELANTAN'],
        ['03081011', 'JBG GUA MUSANG', 'KELANTAN'],
        ['04031011', 'JBG MELAKA', 'MELAKA'],
        ['05051011', 'JBG NEGERI SEMBILAN', 'NEGERI SEMBILAN'],
        ['06041011', 'JBG PAHANG', 'PAHANG'],
        ['06071011', 'JBG RAUB', 'PAHANG'],
        ['09011011', 'JBG PERLIS', 'PERLIS'],
        ['07041011', 'JBG PULAU PINANG', 'PULAU PINANG'],
        ['08031011', 'JBG PERAK', 'PERAK'],
        ['08061011', 'JBG TAIPING', 'PERAK'],
        ['10051021', 'JBG SELANGOR', 'SELANGOR'],
        ['11041011', 'JBG TERENGGANU', 'TERENGGANU'],
        ['13011011', 'JBG SARAWAK', 'SARAWAK'],
        ['13141061', 'JBG MIRI', 'SARAWAK'],
        ['13101041', 'JBG SIBU', 'SARAWAK'],
        ['12071011', 'JBG SABAH', 'SABAH'],
        ['14011011', 'JBG WP KUALA LUMPUR', 'WP KUALA LUMPUR'],
        ['15011021', 'JBG WP LABUAN', 'WP LABUAN'],
        ['16011028', 'JBG WP PUTRAJAYA', 'WP PUTRAJAYA'],
    ];

    public function run(): void
    {
        $this->seedKategoriTree();
        $this->seedCawanganJbg();
        $this->seedJawatan();
    }

    private function seedKategoriTree(): void
    {
        // 1) kn_kategori (4) from the fixed jenis map.
        $katByJenis = [];
        foreach (self::JENIS as $kod => $nama) {
            $katByJenis[$kod] = KnKategori::updateOrCreate(['kod' => $kod], ['nama' => $nama, 'aktif' => true])->id;
        }

        // 2) kn_kategori_kes (10) from DISTINCT ref_kes (jenis_kes, kategori_kes).
        $kesByKey = []; // "JENIS|kategori_kes" => id
        $rows = DB::table('ref_kes')
            ->select('jenis_kes', 'kategori_kes')->whereNotNull('kategori_kes')->where('kategori_kes', '<>', '')
            ->distinct()->get();
        foreach ($rows as $r) {
            $katId = $katByJenis[$r->jenis_kes] ?? null;
            if (! $katId) { continue; } // unknown jenis — log + skip
            $id = KnKategoriKes::updateOrCreate(
                ['kategori_id' => $katId, 'nama' => $r->kategori_kes],
                ['aktif' => true]
            )->id;
            $kesByKey[$r->jenis_kes.'|'.$r->kategori_kes] = $id;
        }

        // 3) kn_subkategori (139) — every ref_kes row, FK to kategori_kes.
        foreach (DB::table('ref_kes')->get() as $rk) {
            $kesId = $kesByKey[$rk->jenis_kes.'|'.$rk->kategori_kes] ?? null;
            if (! $kesId) { continue; } // no subcategory parent — skip (log)
            KnSubkategori::updateOrCreate(
                ['kod' => $rk->id_kes],
                ['kategori_kes_id' => $kesId, 'nama' => $rk->deskripsi, 'aktif' => (string) $rk->aktif_kes === '1']
            );
        }
    }

    private function seedCawanganJbg(): void
    {
        $negeriByNama = DB::table('ref_negeri')->pluck('id', 'nama'); // nama => id

        // 1) 23 legacy branches.
        foreach (self::CAWANGAN_JBG as [$kod, $nama, $negeriNama]) {
            CawanganJbg::updateOrCreate(
                ['kod' => $kod],
                ['nama' => $nama, 'negeri_id' => $negeriByNama[$negeriNama] ?? null, 'aktif' => true]
            );
        }

        // 2) Reconcile live scope values not already a kod (scope continuity).
        $live = DB::table('users')->whereNotNull('cawangan')->where('cawangan', '<>', '')->distinct()->pluck('cawangan')
            ->merge(DB::table('forms')->whereNotNull('cawangan')->where('cawangan', '<>', '')->distinct()->pluck('cawangan'))
            ->unique();
        foreach ($live as $val) {
            CawanganJbg::firstOrCreate(['kod' => $val], ['nama' => $val, 'aktif' => true]);
        }
    }

    private function seedJawatan(): void
    {
        $jawatan = DB::table('pegawai_jbg')->whereNotNull('jawatan')->where('jawatan', '<>', '')->distinct()->pluck('jawatan');
        foreach ($jawatan as $j) {
            RefJawatan::updateOrCreate(['nama' => $j], ['aktif' => true]);
        }
    }
}
```
(Bilik import kept for symmetry though bilik ships empty; remove the unused `use Bilik` line if your linter flags it.)

- [ ] **Step 2: Wire into DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php`, add `Batch8MasterSeeder::class` to the `$this->call([...])` array (after `RolePermissionSeeder`).

- [ ] **Step 3: Run + verify**

Run: `php artisan db:seed --class=Batch8MasterSeeder`
Verify: `php artisan tinker --execute="echo 'kat='.\App\Models\KnKategori::count().' kes='.\App\Models\KnKategoriKes::count().' sub='.\App\Models\KnSubkategori::count().' jbg='.\App\Models\CawanganJbg::count().' jaw='.\App\Models\RefJawatan::count();"`
Expected: kat=4, kes=10, sub≈139, jbg≥23, jaw≥0.

- [ ] **Step 4: Write the seed reconciliation test**

`tests/Feature/Batch8SeedTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\CawanganJbg;
use App\Models\KnKategori;
use App\Models\KnKategoriKes;
use App\Models\KnSubkategori;
use Database\Seeders\Batch8MasterSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Batch8SeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql'); DB::reconnect('mysql');
        (new Batch8MasterSeeder())->run();
    }

    public function test_kategori_tree_seeded(): void
    {
        $this->assertSame(4, KnKategori::count());
        $this->assertGreaterThanOrEqual(10, KnKategoriKes::count());
        $this->assertGreaterThan(100, KnSubkategori::count());
        // every kategori_kes belongs to a real kategori
        $this->assertSame(0, KnKategoriKes::whereNotIn('kategori_id', KnKategori::pluck('id'))->count());
        // every subkategori belongs to a real kategori_kes
        $this->assertSame(0, KnSubkategori::whereNotIn('kategori_kes_id', KnKategoriKes::pluck('id'))->count());
    }

    public function test_cawangan_covers_every_live_scope_value(): void
    {
        $live = DB::table('users')->whereNotNull('cawangan')->where('cawangan', '<>', '')->distinct()->pluck('cawangan')
            ->merge(DB::table('forms')->whereNotNull('cawangan')->where('cawangan', '<>', '')->distinct()->pluck('cawangan'))
            ->unique();
        $kods = CawanganJbg::pluck('kod');
        foreach ($live as $val) {
            $this->assertTrue($kods->contains($val), "cawangan_jbg missing live scope value: {$val}");
        }
    }
}
```
Run: `php artisan test --filter=Batch8SeedTest` → green.

- [ ] **Step 5: Commit**
```bash
git add database/seeders/Batch8MasterSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Batch8SeedTest.php
git commit -m "feat(batch8): master seeder (kategori tree from ref_kes, 23 branches + scope reconcile, jawatan)"
```

---

## Tasks 5–9: Tetapan CRUD (scaffold template + per-master)

All 5 masters use the SAME thin-controller + Blade scaffold (RefKesController / `ref-kes/*` pattern). **Task 5 builds the canonical template (Cawangan JKM — simplest 4-field master) in full.** Tasks 6–9 reuse that exact scaffold with the field/name deltas given. The CRUD test for all masters lives in one `Batch8MasterCrudTest`.

### Task 5: Cawangan JKM CRUD (canonical template)

**Files:**
- Create: `app/Http/Controllers/CawanganJkmController.php`, `app/Http/Requests/CawanganJkmRequest.php`
- Create: `resources/views/cawangan-jkm/index.blade.php`, `form.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/staff.blade.php`
- Test: `tests/Feature/Batch8MasterCrudTest.php`

- [ ] **Step 1: Write the CRUD test (template assertions for JKM)**

`tests/Feature/Batch8MasterCrudTest.php`:
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TestUsersSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class Batch8MasterCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'iguaman_2in1']);
        DB::purge('mysql'); DB::reconnect('mysql');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        (new RolePermissionSeeder())->run();
        (new TestUsersSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        parent::tearDown();
    }

    private function cleanup(): void
    {
        DB::table('cawangan_jkm')->where('kod', 'like', 'UJI%')->delete();
        DB::table('cawangan_penjara')->where('kod', 'like', 'UJI%')->delete();
        DB::table('ref_jawatan')->where('nama', 'like', 'UJIAN%')->delete();
        DB::table('kn_kategori')->where('nama', 'like', 'UJIAN%')->delete();
    }

    private function user(string $email): User { return User::where('email', $email)->firstOrFail(); }

    public function test_pegawai_cannot_reach_jkm(): void
    {
        $this->actingAs($this->user('pegawai@test.local'))->get(route('cawangan-jkm.index'))
            ->assertRedirect(route('system.utama'));
    }

    public function test_supervisor_sees_jkm(): void
    {
        $this->actingAs($this->user('koordinator@test.local'))->get(route('cawangan-jkm.index'))->assertOk();
    }

    public function test_jkm_create(): void
    {
        $this->actingAs($this->user('koordinator@test.local'))->post(route('cawangan-jkm.store'), [
            'kod' => 'UJI01', 'nama' => 'UJIAN JKM', 'aktif' => '1',
        ])->assertRedirect(route('cawangan-jkm.index'));
        $this->assertDatabaseHas('cawangan_jkm', ['kod' => 'UJI01', 'nama' => 'UJIAN JKM']);
    }
}
```
Run: `php artisan test --filter=Batch8MasterCrudTest` → FAIL (route undefined).

- [ ] **Step 2: Controller**

`app/Http/Controllers/CawanganJkmController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\CawanganJkmRequest;
use App\Models\CawanganJkm;
use App\Models\RefNegeri;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CawanganJkmController extends Controller
{
    public function index(Request $request): View
    {
        $rows = CawanganJkm::query()
            ->when($request->input('q'), fn ($w, $v) => $w->where(fn ($s) => $s
                ->where('kod', 'like', "%{$v}%")->orWhere('nama', 'like', "%{$v}%")))
            ->orderBy('nama')->paginate(25)->withQueryString();

        return view('cawangan-jkm.index', ['rows' => $rows, 'filters' => $request->only('q')]);
    }

    public function create(): View
    {
        return view('cawangan-jkm.form', ['row' => new CawanganJkm(), 'mode' => 'create', 'negeri' => RefNegeri::orderBy('nama')->get()]);
    }

    public function store(CawanganJkmRequest $request): RedirectResponse
    {
        $row = CawanganJkm::create($request->validated());
        Audit::log('cawangan_jkm', $row->id, Audit::INSERT, "Cawangan JKM ditambah: {$row->nama}");

        return redirect()->route('cawangan-jkm.index')->with('status', 'Cawangan JKM ditambah.');
    }

    public function edit(CawanganJkm $cawangan_jkm): View
    {
        return view('cawangan-jkm.form', ['row' => $cawangan_jkm, 'mode' => 'edit', 'negeri' => RefNegeri::orderBy('nama')->get()]);
    }

    public function update(CawanganJkmRequest $request, CawanganJkm $cawangan_jkm): RedirectResponse
    {
        $cawangan_jkm->update($request->validated());
        Audit::log('cawangan_jkm', $cawangan_jkm->id, Audit::UPDATE, "Cawangan JKM dikemaskini: {$cawangan_jkm->nama}");

        return redirect()->route('cawangan-jkm.index')->with('status', 'Cawangan JKM dikemaskini.');
    }

    public function destroy(CawanganJkm $cawangan_jkm): RedirectResponse
    {
        // Soft-disable rather than hard delete (master may be referenced downstream).
        $cawangan_jkm->update(['aktif' => false]);
        Audit::log('cawangan_jkm', $cawangan_jkm->id, Audit::UPDATE, "Cawangan JKM dinyahaktif: {$cawangan_jkm->nama}");

        return redirect()->route('cawangan-jkm.index')->with('status', 'Cawangan JKM dinyahaktifkan.');
    }
}
```

- [ ] **Step 3: FormRequest**

`app/Http/Requests/CawanganJkmRequest.php`:
```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CawanganJkmRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('selenggara.cawangan') ?? false; }

    public function rules(): array
    {
        $id = $this->route('cawangan_jkm')?->id;
        return [
            'kod' => ['required', 'string', 'max:50', Rule::unique('cawangan_jkm', 'kod')->ignore($id)],
            'nama' => ['required', 'string', 'max:255'],
            'negeri_id' => ['nullable', 'integer', 'exists:ref_negeri,id'],
            'aktif' => ['nullable', 'in:0,1'],
        ];
    }

    public function attributes(): array { return ['kod' => 'kod', 'nama' => 'nama', 'negeri_id' => 'negeri']; }
}
```

- [ ] **Step 4: Views**

`resources/views/cawangan-jkm/index.blade.php` — `@extends('layouts.staff')`; `tap-head` with title "Cawangan JKM" + a "Tambah" button → `route('cawangan-jkm.create')`; `session('status')` flash; a table (kod, nama, negeri->nama, aktif badge, Edit link → `cawangan-jkm.edit`); `{{ $rows->links() }}`; a search form (`q`). Mirror `resources/views/ref-kes/index.blade.php` markup/classes.

`resources/views/cawangan-jkm/form.blade.php` — based on `ref-kes/form.blade.php`:
```blade
@extends('layouts.staff')
@section('title', $mode === 'create' ? 'Tambah Cawangan JKM' : 'Kemaskini Cawangan JKM')
@php
    $isCreate = $mode === 'create';
    $action = $isCreate ? route('cawangan-jkm.store') : route('cawangan-jkm.update', $row);
    $val = fn (string $f) => old($f, $row->$f);
@endphp
@section('content')
    <div class="tap-head">
        <div><h1 class="tap-head__title">{{ $isCreate ? 'Tambah' : 'Kemaskini' }} Cawangan JKM<span class="dot"></span></h1></div>
        <div class="tap-head__cluster"><a href="{{ route('cawangan-jkm.index') }}" class="tap-head__btn">Batal</a></div>
    </div>
    @if ($errors->any())<div class="formerr" style="margin-bottom:16px;">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ $action }}">
        @csrf @unless ($isCreate) @method('PUT') @endunless
        <div class="tap-card" style="margin-bottom:18px;">
            <div class="wiz-grid">
                <div class="wiz-field"><label class="wiz-field__label">Kod *</label>
                    <input class="wiz-field__input" name="kod" value="{{ $val('kod') }}" maxlength="50" required>
                    @error('kod')<div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div>@enderror</div>
                <div class="wiz-field"><label class="wiz-field__label">Nama *</label>
                    <input class="wiz-field__input" name="nama" value="{{ $val('nama') }}" maxlength="255" required>
                    @error('nama')<div class="wiz-field__hint" style="color:var(--danger)">{{ $message }}</div>@enderror</div>
                <div class="wiz-field"><label class="wiz-field__label">Negeri</label>
                    <select class="wiz-field__select" name="negeri_id">
                        <option value="">— Pilih —</option>
                        @foreach ($negeri as $n)<option value="{{ $n->id }}" @selected((string) $val('negeri_id') === (string) $n->id)>{{ $n->nama }}</option>@endforeach
                    </select></div>
                <div class="wiz-field"><label class="wiz-field__label">Status</label>
                    <select class="wiz-field__select" name="aktif">
                        <option value="1" @selected((string) $val('aktif') === '1' || $val('aktif') === null)>Aktif</option>
                        <option value="0" @selected((string) $val('aktif') === '0')>Tidak Aktif</option>
                    </select></div>
            </div>
        </div>
        <div style="display:flex; gap:10px; justify-content:flex-end;">
            <a href="{{ route('cawangan-jkm.index') }}" class="btn btn--ghost">Batal</a>
            <button type="submit" class="btn btn--primary">{{ $isCreate ? 'Tambah' : 'Simpan' }}</button>
        </div>
    </form>
    @unless ($isCreate)
        <form method="POST" action="{{ route('cawangan-jkm.destroy', $row) }}" onsubmit="return confirm('Nyahaktif cawangan ini?')" style="margin-top:14px;">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn--ghost" style="color:var(--danger);">Nyahaktif</button>
        </form>
    @endunless
@endsection
```

- [ ] **Step 5: Routes + sidebar**

In `routes/web.php`, inside the authenticated staff area add (gated by `selenggara.cawangan`):
```php
    Route::middleware('permission:selenggara.cawangan')->group(function () {
        Route::get('/cawangan-jkm', [\App\Http\Controllers\CawanganJkmController::class, 'index'])->name('cawangan-jkm.index');
        Route::get('/cawangan-jkm/create', [\App\Http\Controllers\CawanganJkmController::class, 'create'])->name('cawangan-jkm.create');
        Route::post('/cawangan-jkm', [\App\Http\Controllers\CawanganJkmController::class, 'store'])->name('cawangan-jkm.store');
        Route::get('/cawangan-jkm/{cawangan_jkm}/edit', [\App\Http\Controllers\CawanganJkmController::class, 'edit'])->name('cawangan-jkm.edit')->whereNumber('cawangan_jkm');
        Route::put('/cawangan-jkm/{cawangan_jkm}', [\App\Http\Controllers\CawanganJkmController::class, 'update'])->name('cawangan-jkm.update')->whereNumber('cawangan_jkm');
        Route::delete('/cawangan-jkm/{cawangan_jkm}', [\App\Http\Controllers\CawanganJkmController::class, 'destroy'])->name('cawangan-jkm.destroy')->whereNumber('cawangan_jkm');
    });
```
In `layouts/staff.blade.php` Pentadbiran block: `@can('selenggara.cawangan')<a href="{{ route('cawangan-jkm.index') }}" class="ws-nav__link">Cawangan JKM</a>@endcan` (use the real sibling nav-link markup).

- [ ] **Step 6: Run + commit**

Run: `php artisan test --filter=Batch8MasterCrudTest` → green.
```bash
git add app/Http/Controllers/CawanganJkmController.php app/Http/Requests/CawanganJkmRequest.php resources/views/cawangan-jkm routes/web.php resources/views/layouts/staff.blade.php tests/Feature/Batch8MasterCrudTest.php
git commit -m "feat(batch8): Cawangan JKM CRUD (canonical master scaffold)"
```

### Task 6: Cawangan Penjara CRUD
Repeat Task 5 verbatim, substituting every token `jkm`→`penjara`, `Jkm`→`Penjara`, `JKM`→`Penjara`, route prefix `cawangan-jkm`→`cawangan-penjara`, model `CawanganJkm`→`CawanganPenjara`, request `CawanganJkmRequest`→`CawanganPenjaraRequest`, table `cawangan_jkm`→`cawangan_penjara`, views dir `cawangan-jkm`→`cawangan-penjara`. Same `selenggara.cawangan` gate (add the route group; same middleware). Add a `test_penjara_create` method to `Batch8MasterCrudTest` mirroring `test_jkm_create`. Commit: `feat(batch8): Cawangan Penjara CRUD`.

### Task 7: Cawangan JBG CRUD (+ weekend field + nested Bilik)
Same scaffold as Task 5 (`cawangan_jbg`, `CawanganJbg`, `CawanganJbgController`, `CawanganJbgRequest`, gate `selenggara.cawangan`) **plus**:
- Form adds a `hari_minggu` select: options `SAT_SUN` ("Sabtu-Ahad"), `FRI_SAT` ("Jumaat-Sabtu"). Rules add `'hari_minggu' => ['nullable','in:SAT_SUN,FRI_SAT']`.
- **Bilik management** on the edit screen: list `$row->bilik`, an add-room form (`POST /cawangan-jbg/{cawangan_jbg}/bilik` → `storeBilik`), and a disable-room action (`DELETE /cawangan-jbg/{cawangan_jbg}/bilik/{bilik}` → `destroyBilik`, sets `aktif=false`). Add `storeBilik`/`destroyBilik` to `CawanganJbgController`:
```php
    public function storeBilik(Request $request, CawanganJbg $cawangan_jbg): RedirectResponse
    {
        $data = $request->validate(['nama' => ['required', 'string', 'max:255']]);
        $cawangan_jbg->bilik()->create($data + ['aktif' => true]);
        Audit::log('bilik', $cawangan_jbg->id, Audit::INSERT, "Bilik ditambah: {$data['nama']} @ {$cawangan_jbg->nama}");
        return back()->with('status', 'Bilik ditambah.');
    }

    public function destroyBilik(CawanganJbg $cawangan_jbg, \App\Models\Bilik $bilik): RedirectResponse
    {
        abort_unless($bilik->cawangan_jbg_id === $cawangan_jbg->id, 404);
        $bilik->update(['aktif' => false]);
        Audit::log('bilik', $bilik->id, Audit::UPDATE, "Bilik dinyahaktif: {$bilik->nama}");
        return back()->with('status', 'Bilik dinyahaktifkan.');
    }
```
Routes (in the `selenggara.cawangan` group): the 6 standard cawangan-jbg routes + the 2 bilik routes (`->name('cawangan-jbg.bilik.store')` / `.destroy`). Add `test_jbg_create` + `test_jbg_add_bilik` to the CRUD test. Commit: `feat(batch8): Cawangan JBG CRUD + bilik management`.

### Task 8: Kategori tree CRUD (3 levels)
**Files:** `app/Http/Controllers/KnKategoriController.php`, `app/Http/Requests/KnKategoriRequest.php` (+ optional kes/sub requests), `resources/views/kn-kategori/{index,kategori-kes,subkategori,form}.blade.php`, routes (gate `selenggara.kategori`).
Pattern: a drill-down. `index` lists `KnKategori`; `kategoriKes(KnKategori)` lists its `kn_kategori_kes`; `subkategori(KnKategoriKes)` lists its `kn_subkategori`. CRUD at each level (create/store/edit/update/destroy-soft-disable), each scoped to its parent. **Delete-guard:** block hard-delete of a `kn_kategori` or `kn_kategori_kes` that has children (`->kategoriKes()->exists()` / `->subkategori()->exists()`) → return error; otherwise soft-disable via `aktif`. Controller methods follow the Task-5 shape with parent-scoped queries. Routes:
```php
    Route::middleware('permission:selenggara.kategori')->group(function () {
        Route::get('/kn-kategori', [\App\Http\Controllers\KnKategoriController::class, 'index'])->name('kn-kategori.index');
        Route::post('/kn-kategori', [\App\Http\Controllers\KnKategoriController::class, 'store'])->name('kn-kategori.store');
        Route::put('/kn-kategori/{kn_kategori}', [\App\Http\Controllers\KnKategoriController::class, 'update'])->name('kn-kategori.update')->whereNumber('kn_kategori');
        Route::delete('/kn-kategori/{kn_kategori}', [\App\Http\Controllers\KnKategoriController::class, 'destroy'])->name('kn-kategori.destroy')->whereNumber('kn_kategori');
        Route::get('/kn-kategori/{kn_kategori}/kes', [\App\Http\Controllers\KnKategoriController::class, 'kategoriKes'])->name('kn-kategori.kes')->whereNumber('kn_kategori');
        Route::post('/kn-kategori/{kn_kategori}/kes', [\App\Http\Controllers\KnKategoriController::class, 'storeKes'])->name('kn-kategori.kes.store')->whereNumber('kn_kategori');
        Route::delete('/kn-kategori-kes/{kn_kategori_kes}', [\App\Http\Controllers\KnKategoriController::class, 'destroyKes'])->name('kn-kategori.kes.destroy')->whereNumber('kn_kategori_kes');
        Route::get('/kn-kategori-kes/{kn_kategori_kes}/sub', [\App\Http\Controllers\KnKategoriController::class, 'subkategori'])->name('kn-kategori.sub')->whereNumber('kn_kategori_kes');
        Route::post('/kn-kategori-kes/{kn_kategori_kes}/sub', [\App\Http\Controllers\KnKategoriController::class, 'storeSub'])->name('kn-kategori.sub.store')->whereNumber('kn_kategori_kes');
        Route::delete('/kn-subkategori/{kn_subkategori}', [\App\Http\Controllers\KnKategoriController::class, 'destroySub'])->name('kn-kategori.sub.destroy')->whereNumber('kn_subkategori');
    });
```
Add to CRUD test: `test_pegawai_cannot_reach_kategori` (redirect), `test_supervisor_creates_kategori`, `test_kategori_with_children_cannot_hard_delete`. Commit: `feat(batch8): Kategori tree CRUD (kategori/kes/subkategori)`.

### Task 9: Jawatan CRUD
Repeat Task 5 with the simplest shape: model `RefJawatan` (fields: `nama`, `aktif` only — no kod, no negeri), gate `selenggara.jawatan`, route prefix `jawatan`, views `resources/views/jawatan/`. `RefJawatanRequest`: `'nama' => ['required','string','max:255'], 'aktif' => ['nullable','in:0,1']`, `authorize` → `can('selenggara.jawatan')`. Add `test_jawatan_create`. Commit: `feat(batch8): Jawatan CRUD`.

---

## Task 10: Full suite + branch finish

- [ ] **Step 1: Run the full suite**

Run: `php artisan test`
Expected: ALL green (Batch8 seed/CRUD + Batch7 + Phase/Permohonan/Hardening). Fix any regression (most likely a fixture missing a Spatie role or a sidebar `@can` typo).

- [ ] **Step 2: Verify routes resolve**

Run: `php artisan route:list --name=cawangan` and `--name=kn-kategori` and `--name=jawatan` → confirm all gated by `permission:selenggara.*`, no comma-delimited middleware (batch-7 lesson; the guard test in `Batch7RbacMatrixTest` also enforces this).

- [ ] **Step 3: Finish the branch**

Use superpowers:finishing-a-development-branch (verify tests → present merge/PR options). Do NOT auto-merge to `main` (deploy branch) — present options. Deploy needs `php artisan migrate` + `php artisan db:seed --class=Batch8MasterSeeder` via SSH (port 65002) after merge.

---

## Self-review (author checklist — completed)

**Spec coverage:** §3 schema → T1,T2 (all 8 tables). §4 scope reconcile → T4 seeder + Batch8SeedTest coverage assertion. §5 RBAC (+3) → T3. §6 seed (ref_kes tree / 23 branches / live reconcile / jawatan / empty jkm-penjara-bilik) → T4. §7 CRUD UI (5 families + soft-disable + delete-guard) → T5–T9. §8 testing → Batch8SeedTest + Batch8MasterCrudTest + T3 count bump. §9 risks (scope coverage, concurrent seeder edit, hot-file timing) → pre-flight + T3 read-first + T4 coverage test.

**Placeholder scan:** Tasks 6/7/9 use explicit "repeat Task 5 with these substitutions" (the scaffold is character-identical; the deltas are exact). Task 8's per-level controller methods follow the Task-5 shape with stated parent-scoping + the full route block is given. Index-view markup references the concrete `ref-kes/index.blade.php` template. No "TBD".

**Type/name consistency:** table names, model names, route names, permission keys (`selenggara.cawangan`/`.kategori`/`.jawatan`), and FK columns (`negeri_id`, `cawangan_jbg_id`, `kategori_id`, `kategori_kes_id`) are consistent across migrations, models, seeder, controllers, routes, and tests. Route-model binding params match snake_case table names (`{cawangan_jkm}`, `{kn_kategori}`).

**Known timing note:** branch from `main` after batch-7 merges + concurrent EPIC F/G settles (the seeder + routes + sidebar are hot files). Re-read `RolePermissionSeeder` before Task 3 (concurrent `selenggara.cuti`).

---

## Execution handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-30-batch8-masters.md`. Two execution options:

1. **Subagent-Driven (recommended)** — fresh subagent per task, two-stage review between tasks.
2. **Inline Execution** — execute tasks in this session with checkpoints.

Which approach? (Note: execution should wait until batch-7 is merged to `main` and the concurrent EPIC work settles, per the timing note.)
