<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Demo accounts for the login page. Strip / replace before real production (use MFA+SSO).
// Password for all demo accounts: "password".
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        // Never plant known-password demo accounts outside local/testing (prod backdoor guard).
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $users = [
            ['name' => 'Demo Admin', 'email' => 'demo@example.com'],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => Hash::make('password'), 'must_change_password' => false]
            );
        }
    }
}
