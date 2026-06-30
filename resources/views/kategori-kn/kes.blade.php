@extends('layouts.staff')

@section('title', 'Kategori Kes')

@section('content')
    <div class="tap-head">
        <div>
            <h1 class="tap-head__title">{{ $kategori->jenis_kategori }}<span class="dot"></span></h1>
            <p class="tap-head__sub"><a href="{{ route('kategori-kn.index') }}">Jenis Khidmat</a> › Kategori kes (aras 2)</p>
        </div>
        <div class="tap-head__cluster">
            <a href="{{ route('kategori-kn.index') }}" class="tap-head__btn">← Kembali</a>
        </div>
    </div>

    @if (session('status'))
        <div class="formerr" style="color: var(--success); background: rgba(16,185,129,0.08); border-color: rgba(16,185,129,0.18); margin-bottom:14px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="formerr" style="margin-bottom:14px;">{{ $errors->first() }}</div>
    @endif

    <div class="tap-card" style="margin-bottom:18px;">
        <div class="tap-card__eyebrow">Tambah Kategori Kes</div>
        <form method="POST" action="{{ route('kategori-kn.kes.store', $kategori) }}" style="display:flex; gap:8px;">
            @csrf
            <input class="wiz-field__input" name="nama" placeholder="Nama kategori kes baharu…" maxlength="255" required style="flex:1;">
            <button type="submit" class="btn btn--primary">+ Tambah</button>
        </form>
    </div>

    <div class="tap-card">
        <table style="width:100%; border-collapse:collapse;">
            @forelse ($kesList as $row)
                <tr style="border-bottom:1px solid var(--line);">
                    <td style="padding:8px 0; width:100%;">
                        <form method="POST" action="{{ route('kategori-kn.kes.update', $row) }}" style="display:flex; gap:8px; align-items:center;">
                            @csrf @method('PUT')
                            <input class="wiz-field__input" name="nama" value="{{ $row->nama }}" maxlength="255" required style="flex:1;">
                            <button type="submit" class="btn btn--ghost">Simpan</button>
                        </form>
                    </td>
                    <td style="white-space:nowrap; padding:0 8px;">
                        <a href="{{ route('kategori-kn.sub', $row) }}" class="tap-head__btn">{{ $row->subkategori_count }} sub →</a>
                    </td>
                    <td style="text-align:right;">
                        <form method="POST" action="{{ route('kategori-kn.kes.destroy', $row) }}" onsubmit="return confirm('Padam kategori kes ini? Subkategori turut dipadam.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="tap-head__btn" style="color:var(--danger);">✕</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td style="padding:10px 0; color:var(--mute);">Tiada kategori kes.</td></tr>
            @endforelse
        </table>
    </div>
@endsection
