<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Akses - per-role permission matrix. Gated permission:urus.peranan.
class RolePermissionController extends Controller
{
    /**
     * Permissions that may NOT be granted to any non-admin role through the matrix
     * UI. `urus.peranan` is the keys-to-the-kingdom: it gates this very screen, so
     * handing it to a lower role is a persistent privilege-escalation vector.
     */
    public const PROTECTED_PERMISSIONS = ['urus.peranan'];

    public function edit(Role $role): View
    {
        $all = Permission::orderBy('name')->get();
        $grouped = $all->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('peranan.akses', [
            'role' => $role,
            'grouped' => $grouped,
            'assigned' => $role->permissions->pluck('name')->all(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        // admin is super-admin via Gate::before - its matrix is decorative, and
        // editing it risks emptying the only super-admin's access. Refuse outright.
        if ($role->name === User::ROLE_ADMIN) {
            return back()->withErrors(['permissions' => 'Matriks akses Admin tidak boleh diubah (Admin = akses penuh).']);
        }

        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $perms = $data['permissions'] ?? [];

        // Escalation guard: a non-admin role may never be granted a protected permission.
        $blocked = array_values(array_intersect($perms, self::PROTECTED_PERMISSIONS));
        if ($blocked !== []) {
            return back()->withErrors([
                'permissions' => 'Akses terlindung tidak boleh diberi kepada peranan ini: '.implode(', ', $blocked),
            ]);
        }

        $role->syncPermissions($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles', $role->id, Audit::UPDATE, "Akses peranan dikemaskini: {$role->name}");

        return redirect()->route('peranan.index')->with('status', 'Akses peranan dikemaskini.');
    }
}
