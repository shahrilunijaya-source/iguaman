<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Pengurusan Pengguna — user/account management (users). Admin-gated (routes).
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['role', 'user_type', 'q']);

        $users = User::query()
            ->when($filters['role'] ?? null, fn ($w, $v) => $w->where('role', $v))
            ->when($filters['user_type'] ?? null, fn ($w, $v) => $w->where('user_type', $v))
            ->when($filters['q'] ?? null, fn ($w, $v) => $w->where(fn ($s) => $s
                ->where('name', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")
                ->orWhere('username', 'like', "%{$v}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('pengguna.index', [
            'users' => $users,
            'filters' => $filters,
            'roleList' => self::ROLES,
        ]);
    }

    public function create(): View
    {
        return view('pengguna.form', ['user' => new User(), 'mode' => 'create']);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'] ?? null,
            'password' => $data['password'], // cast 'hashed' on the model hashes this
            'role' => $data['role'],
            'user_type' => $data['user_type'],
            'cawangan' => $data['cawangan'] ?? null,
            'nokp' => $data['nokp'] ?? null,
            'is_active' => true,
            'must_change_password' => $data['user_type'] === User::TYPE_STAFF,
        ]);

        Audit::log('users', $user->id, Audit::INSERT, "Pengguna ditambah: {$user->name} ({$user->email})");

        return redirect()->route('pengguna.index')->with('status', 'Pengguna ditambah.');
    }

    public function edit(User $user): View
    {
        return view('pengguna.form', ['user' => $user, 'mode' => 'edit']);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'] ?? null,
            'role' => $data['role'],
            'user_type' => $data['user_type'],
            'cawangan' => $data['cawangan'] ?? null,
            'nokp' => $data['nokp'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? 0),
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password']; // cast 'hashed' re-hashes
        }

        $user->update($payload);

        Audit::log('users', $user->id, Audit::UPDATE, "Pengguna dikemaskini: {$user->name} ({$user->email})");

        return redirect()->route('pengguna.index')->with('status', 'Pengguna dikemaskini.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak boleh memadam akaun sendiri.']);
        }

        $nama = $user->name;
        $emel = $user->email;
        $id = $user->id;
        $user->delete();

        Audit::log('users', $id, Audit::DELETE, "Pengguna dipadam: {$nama} ({$emel})");

        return redirect()->route('pengguna.index')->with('status', 'Pengguna dipadam.');
    }

    /** Selectable roles for filter + form (label keyed by role constant). */
    public const ROLES = [
        User::ROLE_ADMIN => 'Admin',
        User::ROLE_PENGARAH => 'Pengarah',
        User::ROLE_KOORDINATOR => 'Koordinator',
        User::ROLE_PEGAWAI => 'Pegawai',
        User::ROLE_PEGUAM => 'Peguam',
    ];
}
