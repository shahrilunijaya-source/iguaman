<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Phase-1 ETL: copy legacy `sistemspk` into the unified `iguaman_2in1` schema.
 *  - 20 domain tables: verbatim copy (column names preserved).
 *  - 3 legacy user tables -> single `users` with bcrypt passwords + role/user_type mapping.
 *
 * Both DBs live on the same MySQL server, so this uses fully-qualified cross-DB SQL on the
 * default connection. Re-runnable: truncates target tables first.
 *
 *   php artisan legacy:import --source=sistemspk
 */
class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import {--source=sistemspk : Source database name} {--fresh : Truncate target tables before import}';

    protected $description = 'Import legacy sistemspk data into the unified iguaman_2in1 schema';

    /** Domain tables copied verbatim (same columns in source + target). */
    private array $verbatim = [
        'ref_negeri', 'ref_lokasi_berguam', 'ref_kes', 'ref_cuti', 'mahkamah_sivil',
        'mahkamah_syariah', 'pegawai_jbg', 'items', 'posters', 'butiran_oyd',
        'butiran_peguam_panel', 'butiran_peguam_panel_2', 'forms', 'laporan_kes',
        'sejarah_pegawai', 'sejarah_peguam_panel', 'sejarah_sidang', 'uploaded_files',
        'audit_trail',
    ];

    /** peguam_panel: target has an added surrogate `id`, so copy explicit columns. */
    private array $peguamPanelCols = [
        'nama_peguam', 'tarikh_penugasan_peguam_panel', 'kp_peguam', 'tel_peguam',
        'emel_peguam', 'nama_firma', 'alamat_firma_1', 'alamat_firma_2', 'alamat_firma_3',
        'poskod_firma', 'negeri_firma', 'tel_firma',
    ];

    public function handle(): int
    {
        $src = $this->option('source');

        $exists = DB::selectOne(
            'SELECT COUNT(*) AS n FROM information_schema.schemata WHERE schema_name = ?',
            [$src]
        );
        if (! $exists || (int) $exists->n === 0) {
            $this->error("Source database `{$src}` not found on this MySQL server.");

            return self::FAILURE;
        }

        $this->info("Importing from `{$src}` -> unified schema...");
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        // Legacy data contains '0000-00-00' zero-dates — relax strict mode for the copy.
        DB::statement("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");

        if ($this->option('fresh')) {
            $this->truncateTargets();
        }

        $this->copyVerbatim($src);
        $this->copyPeguamPanel($src);
        $this->importUsers($src);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->newLine();
        $this->info('Legacy import complete.');
        $this->warn('Passwords were bcrypt-rehashed from legacy plaintext. Force a reset before production.');

        return self::SUCCESS;
    }

    private function truncateTargets(): void
    {
        foreach (array_merge($this->verbatim, ['peguam_panel', 'users']) as $t) {
            DB::table($t)->truncate();
        }
        $this->line('  truncated target tables');
    }

    private function copyVerbatim(string $src): void
    {
        foreach ($this->verbatim as $t) {
            DB::statement("INSERT INTO `{$t}` SELECT * FROM `{$src}`.`{$t}`");
            $this->line(sprintf('  %-26s %d rows', $t, DB::table($t)->count()));
        }
    }

    private function copyPeguamPanel(string $src): void
    {
        $cols = '`'.implode('`,`', $this->peguamPanelCols).'`';
        DB::statement("INSERT INTO `peguam_panel` ({$cols}) SELECT {$cols} FROM `{$src}`.`peguam_panel`");
        $this->line(sprintf('  %-26s %d rows', 'peguam_panel', DB::table('peguam_panel')->count()));
    }

    /** Unify users + users_peguam_panel_2/_3 into `users`, bcrypting passwords, deduping emails. */
    private function importUsers(string $src): void
    {
        $seenEmail = [];
        $now = now();
        $batch = [];

        // Staff
        foreach (DB::select("SELECT * FROM `{$src}`.`users`") as $u) {
            $email = $this->resolveEmail($u->emel ?? null, $u->username ?? null, 'staff'.$u->id, $seenEmail);
            $batch[] = [
                'name' => $u->nama ?: ($u->username ?: 'Staff '.$u->id),
                'email' => $email,
                'username' => $u->username ?? null,
                'password' => Hash::make($u->kata_laluan ?: Str::random(16)),
                'user_type' => 'staff',
                'role' => $this->staffRole((int) $u->peranan),
                'cawangan' => $u->cawangan ?? null,
                'nokp' => $u->nokp ?? null,
                'id_peguam_panel' => null,
                'is_active' => ($u->status_aktif ?? '1') === '1',
                'last_login_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Lawyers (two legacy tiers)
        foreach (['users_peguam_panel_2', 'users_peguam_panel_3'] as $lawyerTable) {
            foreach (DB::select("SELECT * FROM `{$src}`.`{$lawyerTable}`") as $u) {
                $email = $this->resolveEmail($u->emel ?? null, $u->id_peguam_panel ?? null, 'peguam'.$u->id, $seenEmail);
                $batch[] = [
                    'name' => $u->nama ?: ('Peguam '.$u->id),
                    'email' => $email,
                    'username' => $u->id_peguam_panel ?? null,
                    'password' => Hash::make($u->kata_laluan ?: Str::random(16)),
                    'user_type' => 'lawyer',
                    'role' => 'peguam',
                    'cawangan' => null,
                    'nokp' => null,
                    'id_peguam_panel' => $u->id_peguam_panel ?? null,
                    'is_active' => true,
                    'last_login_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            DB::table('users')->insert($chunk);
        }
        $this->line(sprintf('  %-26s %d rows (unified)', 'users', DB::table('users')->count()));
    }

    /**
     * Legacy staff peranan -> role. Confirmed from log_masuk.php redirect logic:
     *   1 => admin_dashboard, 2 => pengarah_dashboard, else => dashboard (ordinary officer).
     */
    private function staffRole(int $peranan): string
    {
        return match ($peranan) {
            1 => 'admin',
            2 => 'pengarah',
            default => 'pegawai',
        };
    }

    /** Ensure a unique, non-null email. Synthesizes a placeholder when legacy email is blank/duplicate. */
    private function resolveEmail(?string $email, ?string $fallbackId, string $unique, array &$seen): string
    {
        $email = trim((string) $email);
        if ($email === '' || isset($seen[strtolower($email)])) {
            $base = $fallbackId ? Str::slug($fallbackId, '.') : $unique;
            $email = $base.'@legacy.local';
            $i = 1;
            while (isset($seen[strtolower($email)])) {
                $email = $base.'+'.$i++.'@legacy.local';
            }
        }
        $seen[strtolower($email)] = true;

        return $email;
    }
}
