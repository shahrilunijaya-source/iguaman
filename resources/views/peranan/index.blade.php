@extends('layouts.staff')

@section('title', 'Peranan & Akses')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Peranan &amp; Akses<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($roles->count()) }}</strong> peranan</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('peranan.create') }}" class="btn btn--primary" style="height:38px;">+ Tambah Peranan</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 2fr 1fr 1fr 1.4fr;">
            <div class="tap-table__th">Peranan</div>
            <div class="tap-table__th">Bil. Akses</div>
            <div class="tap-table__th">Bil. Pengguna</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($roles as $role)
            @php $isSystem = in_array($role->name, $systemRoles, true); @endphp
            <div class="tap-row" style="grid-template-columns: 2fr 1fr 1fr 1.4fr;">
                <div class="tap-row__title">
                    {{ ucfirst($role->name) }}
                    @if ($isSystem)<span class="pill pill--received" style="margin-left:6px;">Sistem</span>@endif
                </div>
                <div class="tap-row__tujuan">{{ number_format($role->permissions_count) }}</div>
                <div class="tap-row__tujuan">{{ number_format($role->users_count) }}</div>
                <div style="text-align:right; display:flex; gap:6px; justify-content:flex-end; align-items:center;">
                    <a href="{{ route('peranan.akses.edit', $role) }}" class="tap-head__btn">Akses</a>
                    @unless ($isSystem)
                        <a href="{{ route('peranan.edit', $role) }}" class="tap-head__btn">Edit</a>
                        <form method="POST" action="{{ route('peranan.destroy', $role) }}" onsubmit="return confirm('Padam peranan ini?')" style="display:inline;">
                            @csrf @method('DELETE')
                            <button type="submit" class="tap-head__btn" style="color:var(--danger);">Padam</button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada peranan<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah peranan baharu.</div>
            </div>
        @endforelse
    </div>
@endsection
