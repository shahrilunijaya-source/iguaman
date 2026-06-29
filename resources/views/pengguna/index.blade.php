@extends('layouts.staff')

@section('title', 'Pengurusan Pengguna')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Pengurusan Pengguna<span class="dot"></span></h1>
            <p class="tap-head__sub"><strong>{{ number_format($users->total()) }}</strong> akaun pengguna</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('pengguna.create') }}" class="btn btn--primary" style="height:38px;">+ Tambah Pengguna</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('pengguna.index') }}" class="tap-filters">
        <select name="role" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Peranan</option>
            @foreach ($roleList as $value => $label)
                <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="user_type" class="tap-chip" onchange="this.form.submit()">
            <option value="">Semua Jenis</option>
            <option value="staff" @selected(($filters['user_type'] ?? '') === 'staff')>Staf</option>
            <option value="lawyer" @selected(($filters['user_type'] ?? '') === 'lawyer')>Peguam</option>
        </select>
        <div class="tap-search">
            <span class="tap-search__icon">⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari nama, emel atau username…">
        </div>
    </form>

    <div class="tap-table">
        <div class="tap-table__head" style="grid-template-columns: 1.8fr 1.8fr 1.1fr 1fr 90px 90px;">
            <div class="tap-table__th">Nama</div>
            <div class="tap-table__th">Emel</div>
            <div class="tap-table__th">Peranan</div>
            <div class="tap-table__th">Jenis</div>
            <div class="tap-table__th">Status</div>
            <div class="tap-table__th"></div>
        </div>

        @forelse ($users as $row)
            <div class="tap-row" style="grid-template-columns: 1.8fr 1.8fr 1.1fr 1fr 90px 90px;">
                <div class="tap-row__title">{{ $row->name }}</div>
                <div class="tap-row__tujuan">{{ $row->email }}</div>
                <div><span class="pill pill--received">{{ $roleList[$row->role] ?? $row->role }}</span></div>
                <div class="tap-row__tujuan">{{ $row->user_type === 'lawyer' ? 'Peguam' : 'Staf' }}</div>
                <div><span class="pill {{ $row->is_active ? 'pill--received' : 'pill--overdue' }}">{{ $row->is_active ? 'Aktif' : 'Tidak' }}</span></div>
                <div style="text-align:right;"><a href="{{ route('pengguna.edit', $row) }}" class="tap-head__btn">✎</a></div>
            </div>
        @empty
            <div class="dash-empty" style="border:0">
                <div class="dash-empty__title">Tiada pengguna<span class="dot"></span></div>
                <div class="dash-empty__sub">Tambah atau laraskan carian.</div>
            </div>
        @endforelse

        @if ($users->hasPages())
            <div class="tap-page">
                <span>Halaman {{ $users->currentPage() }} / {{ $users->lastPage() }} · {{ number_format($users->total()) }} pengguna</span>
                <div class="tap-page__nav">
                    @if ($users->onFirstPage())
                        <span class="tap-page__btn" style="opacity:.4">← Sebelum</span>
                    @else
                        <a href="{{ $users->previousPageUrl() }}" class="tap-page__btn">← Sebelum</a>
                    @endif
                    @if ($users->hasMorePages())
                        <a href="{{ $users->nextPageUrl() }}" class="tap-page__btn">Seterusnya →</a>
                    @else
                        <span class="tap-page__btn" style="opacity:.4">Seterusnya →</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
@endsection
