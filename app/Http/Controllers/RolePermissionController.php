<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Akses — per-role permission matrix. Gated permission:urus.peranan.
class RolePermissionController extends Controller
{
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
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
        $role->syncPermissions($data['permissions'] ?? []);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles', $role->id, Audit::UPDATE, "Akses peranan dikemaskini: {$role->name}");

        return redirect()->route('peranan.index')->with('status', 'Akses peranan dikemaskini.');
    }
}
