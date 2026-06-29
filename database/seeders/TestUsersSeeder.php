<?php

namespace Database\Seeders;

use App\Models\PeguamPanel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// One test account per role so each privilege path can be exercised end-to-end.
// Password for ALL test accounts: "password". Strip before production.
//   Staff area  (admin/pengarah/koordinator/pegawai) -> /system, /kes, /statistik
//   Lawyer area (peguam)                              -> /peguam
class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Wire the lawyer test account to a real panel record so its profile loads.
        $panelKp = PeguamPanel::where('kp_peguam', '!=', '')->whereNotNull('kp_peguam')->value('kp_peguam');

        $users = [
            ['email' => 'admin@test.local',          'name' => 'Test Admin',          'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_ADMIN],
            ['email' => 'pengarah@test.local',       'name' => 'Test Pengarah',       'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_PENGARAH],
            ['email' => 'koordinator@test.local',    'name' => 'Test Koordinator',    'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_KOORDINATOR],
            ['email' => 'pegawai@test.local',        'name' => 'Test Pegawai',        'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_PEGAWAI],
            ['email' => 'ppuu@test.local',           'name' => 'Test PPUU',           'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_PPUU],
            ['email' => 'pembantu@test.local',       'name' => 'Test Pembantu',       'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_PEMBANTU_TADBIR],
            ['email' => 'kp@test.local',             'name' => 'Test Ketua Pengarah', 'user_type' => User::TYPE_STAFF,  'role' => User::ROLE_KETUA_PENGARAH],
            ['email' => 'peguam@test.local',         'name' => 'Test Peguam',         'user_type' => User::TYPE_LAWYER, 'role' => User::ROLE_PEGUAM, 'id_peguam_panel' => $panelKp],
        ];

        foreach ($users as $u) {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                array_merge($u, [
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    // Demo/test accounts skip the legacy force-reset gate.
                    'must_change_password' => false,
                ])
            );
            $user->syncRoles([$u['role']]);
        }
    }
}
