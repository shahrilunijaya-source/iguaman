<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

// Peranan (role) management. Gated permission:urus.peranan (admin-only via seeder + Gate::before).
class RoleController extends Controller
{
    /** Seeded system roles — cannot be renamed/deleted via UI. */
    public const SYSTEM_ROLES = [
        'admin', 'pengarah', 'koordinator', 'pegawai',
        'ppuu', 'pembantu_tadbir', 'ketua_pengarah', 'peguam',
    ];

    public function index(): View
    {
        return view('peranan.index', [
            'roles' => Role::withCount(['permissions', 'users'])->orderBy('name')->get(),
            'systemRoles' => self::SYSTEM_ROLES,
        ]);
    }

    public function create(): View
    {
        return view('peranan.form', ['role' => new Role(), 'mode' => 'create']);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50', 'unique:roles,name']]);
        Role::findOrCreate($data['name'], 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles', 0, Audit::INSERT, "Peranan ditambah: {$data['name']}");

        return redirect()->route('peranan.index')->with('status', 'Peranan ditambah.');
    }

    public function edit(Role $role): View
    {
        return view('peranan.form', ['role' => $role, 'mode' => 'edit']);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return back()->withErrors(['name' => 'Peranan sistem tidak boleh dinamakan semula.']);
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:50', "unique:roles,name,{$role->id}"]]);
        $role->update(['name' => $data['name']]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()->route('peranan.index')->with('status', 'Peranan dikemaskini.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if (in_array($role->name, self::SYSTEM_ROLES, true)) {
            return back()->withErrors(['peranan' => 'Peranan sistem tidak boleh dipadam.']);
        }
        if ($role->users()->exists()) {
            return back()->withErrors(['peranan' => 'Peranan ini masih digunakan oleh pengguna.']);
        }
        $name = $role->name;
        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles', 0, Audit::DELETE, "Peranan dipadam: {$name}");

        return redirect()->route('peranan.index')->with('status', 'Peranan dipadam.');
    }
}
