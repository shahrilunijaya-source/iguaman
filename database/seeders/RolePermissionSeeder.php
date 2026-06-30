<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds Spatie roles + permissions to mirror the pre-RBAC EnsureRole/role-const access.
 * Idempotent: safe to re-run every deploy. admin = super-admin via Gate::before, so it is
 * not enumerated per-permission (only granted urus.peranan so the matrix UI resolves).
 */
class RolePermissionSeeder extends Seeder
{
    /** All roles (must match User::ROLE_* + lawyer). */
    private const ROLES = [
        'admin', 'pengarah', 'koordinator', 'pegawai',
        'ppuu', 'pembantu_tadbir', 'ketua_pengarah', 'peguam',
    ];

    /** permission => roles granted (admin omitted — Gate::before). */
    private const MATRIX = [
        'system.view'            => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kes.view'               => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kes.create'             => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kes.update'             => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kes.keputusan'          => ['pengarah', 'ketua_pengarah'],
        'pengantaraan.manage'    => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'mahkamah.manage'        => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'lampiran.manage'        => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'cetakan.view'           => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'oyd.manage'             => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'kpi.view'               => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'laporan.view'           => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'statistik.view'         => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'agihan.manage'          => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'agihan.pengarah'        => ['pengarah'],
        'agihan.ppuu'            => ['ppuu', 'koordinator'],
        'agihan.kp'              => ['ketua_pengarah'],
        // Khidmat Nasihat (legal-advisory applications) — batch 9. Citizen/PELANGGAN access deferred to batch 13.
        'khidmat.view'           => ['pembantu_tadbir', 'pegawai', 'koordinator', 'pengarah', 'ketua_pengarah'],
        'khidmat.manage'         => ['pembantu_tadbir', 'pegawai', 'koordinator', 'pengarah', 'ketua_pengarah'],
        'peguam_panel.manage'    => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'peguam.permohonan.view' => ['pengarah', 'koordinator', 'pegawai', 'ppuu', 'pembantu_tadbir', 'ketua_pengarah'],
        'peguam.semak'           => ['ppuu', 'pembantu_tadbir', 'koordinator'],
        'peguam.sokong'          => ['pengarah'],
        'peguam.keputusan'       => ['ketua_pengarah'],
        'selenggara.pegawai'     => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.poster'      => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.ref_kes'     => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.mahkamah_ref'=> ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.cuti'        => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.cawangan'    => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.kategori_kn' => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'selenggara.jawatan'     => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'urus.pengguna'          => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'audit.view'             => ['pengarah', 'koordinator', 'ketua_pengarah'],
        'menu.selenggara'        => ['pengarah', 'koordinator'],
        'cawangan.view-all'      => ['koordinator', 'ketua_pengarah'],
        'urus.peranan'           => ['admin'],
        'lawyer.area'            => ['peguam'],
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (self::ROLES as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach (array_keys(self::MATRIX) as $perm) {
            Permission::findOrCreate($perm, 'web');
        }

        $registrar->forgetCachedPermissions();

        // Build per-role permission lists, then sync.
        $byRole = [];
        foreach (self::MATRIX as $perm => $roles) {
            foreach ($roles as $role) {
                $byRole[$role][] = $perm;
            }
        }
        foreach (self::ROLES as $role) {
            Role::findByName($role, 'web')->syncPermissions($byRole[$role] ?? []);
        }

        $registrar->forgetCachedPermissions();
    }
}
