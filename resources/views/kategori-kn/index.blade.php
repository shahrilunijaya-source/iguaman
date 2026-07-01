@extends('layouts.staff')

@section('title', 'Jenis Khidmat')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">Jenis Khidmat<span class="dot"></span></h1>
            <p class="tap-head__sub">Kategori Khidmat Nasihat (aras 1). Klik untuk urus kategori kes.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <div class="tap-card" style="margin-bottom:18px;">
        <div class="tap-card__eyebrow">Tambah Kategori</div>
        <form method="POST" action="{{ route('kategori-kn.store') }}" style="display:flex; gap:8px;">
            @csrf
            <input class="wiz-field__input" name="jenis_kategori" placeholder="Nama kategori baharu…" aria-label="Nama kategori baharu…" maxlength="255" required style="flex:1;">
            <button type="submit" class="btn btn--primary">+ Tambah</button>
        </form>
    </div>

    <div class="tap-card">
        <table style="width:100%; border-collapse:collapse;">
            @forelse ($kategori as $row)
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:8px 0; width:100%;">
                        <form method="POST" action="{{ route('kategori-kn.update', $row) }}" style="display:flex; gap:8px; align-items:center;">
                            @csrf @method('PUT')
                            <input class="wiz-field__input" name="jenis_kategori" value="{{ $row->jenis_kategori }}" maxlength="255" required style="flex:1;">
                            <label style="font-size:12px; display:inline-flex; gap:4px; align-items:center; color:var(--mute);">
                                <input type="checkbox" name="aktif" value="1" @checked($row->aktif)> Aktif
                            </label>
                            <button type="submit" class="btn btn--ghost">Simpan</button>
                        </form>
                    </td>
                    <td style="white-space:nowrap; padding:0 8px;">
                        <a href="{{ route('kategori-kn.kes', $row) }}" class="tap-head__btn">{{ $row->kategori_kes_count }} kes →</a>
                    </td>
                    <td style="text-align:right;">
                        <form method="POST" action="{{ route('kategori-kn.destroy', $row) }}" onsubmit="return confirm('Padam kategori ini? Semua kategori kes & subkategori turut dipadam.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="tap-head__btn" style="color:var(--danger);">✕</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td style="padding:10px 0; color:var(--mute);">Tiada kategori.</td></tr>
            @endforelse
        </table>
    </div>
@endsection
