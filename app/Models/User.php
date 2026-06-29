<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'username', 'password', 'user_type', 'role', 'cawangan', 'nokp', 'id_peguam_panel', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    // Roles (string). Staff: admin/pengarah/koordinator/pegawai. External: peguam.
    public const ROLE_ADMIN = 'admin';
    public const ROLE_PENGARAH = 'pengarah';
    public const ROLE_KOORDINATOR = 'koordinator';
    public const ROLE_PEGAWAI = 'pegawai';
    public const ROLE_PEGUAM = 'peguam';

    public const TYPE_STAFF = 'staff';
    public const TYPE_LAWYER = 'lawyer';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isStaff(): bool
    {
        return $this->user_type === self::TYPE_STAFF;
    }

    public function isLawyer(): bool
    {
        return $this->user_type === self::TYPE_LAWYER;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /** Staff roles that share the internal (rekod-kes + panel admin) area. */
    public const STAFF_ROLES = [self::ROLE_ADMIN, self::ROLE_PENGARAH, self::ROLE_KOORDINATOR, self::ROLE_PEGAWAI];

    /** Landing route name for this user's area. */
    public function homeRoute(): string
    {
        return $this->isLawyer() ? 'peguam.dashboard' : 'system.utama';
    }

    /** Lawyer login -> panel-lawyer master record (links via IC). Tentative key; confirm at ETL. */
    public function lawyerProfile(): BelongsTo
    {
        return $this->belongsTo(PeguamPanel::class, 'id_peguam_panel', 'kp_peguam');
    }
}
