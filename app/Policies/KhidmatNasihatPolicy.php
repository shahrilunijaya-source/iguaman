<?php

namespace App\Policies;

use App\Models\KhidmatNasihat;
use App\Models\User;

class KhidmatNasihatPolicy
{
    public function view(User $user, KhidmatNasihat $kn): bool
    {
        return $this->owns($user, $kn);
    }

    public function update(User $user, KhidmatNasihat $kn): bool
    {
        return $this->owns($user, $kn);
    }

    private function owns(User $user, KhidmatNasihat $kn): bool
    {
        return $user->isAwam() && (int) $kn->id_pengguna === (int) $user->id;
    }
}
